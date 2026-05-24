<?php

namespace App\Http\Controllers;

use App\Services\AttachmentStore;
use App\Services\KeyStoreService;
use App\Services\PgpService;
use App\Services\ScheduleService;
use App\Services\SnoozeService;
use App\Services\MailboxService;
use App\Services\MailQueue;
use App\Services\SendService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MailController extends Controller
{
    public function __construct(private MailboxService $mail)
    {
    }

    /** GET /webmail — interface webmail : dossiers + liste + message ouvert. */
    public function index(Request $request)
    {
        $loginEmail = $request->session()->get('mail_user');
        $email = $loginEmail;

        // Identity switcher : ?as=<shared_mailbox_id> bascule sur une boîte
        // partagée à laquelle l'utilisateur a un ACL.
        $asSharedId = (int) $request->query('as', 0);
        $sharedMailboxes = \Illuminate\Support\Facades\DB::table('shared_mailbox_acls')
            ->join('shared_mailboxes', 'shared_mailboxes.id', '=', 'shared_mailbox_acls.shared_mailbox_id')
            ->where('shared_mailbox_acls.user_email', strtolower((string) $loginEmail))
            ->where('shared_mailboxes.active', true)
            ->select('shared_mailboxes.id','shared_mailboxes.email','shared_mailboxes.display_name','shared_mailbox_acls.role')
            ->get()->toArray();
        $activeShared = null;
        if ($asSharedId) {
            $activeShared = collect($sharedMailboxes)->firstWhere('id', $asSharedId);
            if ($activeShared) $email = $activeShared->email;
        }

        $folder = (string) ($request->query('folder') ?: 'INBOX');
        $uid    = $request->query('uid');
        $search = trim((string) $request->query('q', ''));

        $error = null;
        $folders = [];
        $messages = [];
        $total = 0;
        $selected = null;
        $quota = null;

        try {
            $folders  = $this->mail->folders($email);
            $res = $this->mail->messages($email, $folder, 40, $search);
            $messages = $res['messages'];
            $total = $res['total'];

            // Filtre snoozés
            $snoozed = app(SnoozeService::class)->active($email);
            $messages = array_values(array_filter($messages, function ($m) use ($folder, $snoozed) {
                return ! isset($snoozed["$folder|{$m['uid']}"]);
            }));
            if ($uid) {
                $selected = $this->mail->message($email, $folder, (int) $uid);
                // Mode conversation : on ajoute la liste des messages du même fil
                if ($selected && (app(SettingsService::class)->get($email)['view_mode'] ?? 'list') === 'thread') {
                    $selected['thread'] = $this->mail->threadOf($email, $folder, $selected['subject'] ?? '');
                }
            }
            $quota = $this->mail->quota($email);
        } catch (\Throwable $e) {
            $error = 'Connexion à la boîte mail impossible : ' . $e->getMessage();
        }

        return Inertia::render('Mailbox', [
            'me'        => $email,
            'me_hash'   => $email ? hash('sha256', strtolower(trim((string) $email))) : '',
            'me_name'   => (string) $request->session()->get('mail_name', ''),
            'settings'  => app(SettingsService::class)->get((string) $email),
            'folders'   => $folders,
            'folder'   => $folder,
            'messages' => $messages,
            'total'    => $total,
            'search'   => $search,
            'selected' => $selected,
            'quota'    => $quota,
            'error'    => $error,
            // Affichage des fonctions IA dans le webmail UNIQUEMENT si activées
            // côté admin dans /admin/settings (off par défaut).
            'enable_noliae_ai' => \App\Services\AppSettings::bool('enable_noliae_ai', false),
            // Identity switcher : liste des boîtes partagées accessibles + boîte active
            'shared_mailboxes' => $sharedMailboxes,
            'as_shared_id'     => $activeShared?->id,
        ]);
    }

    /** POST /webmail/folders — crée un dossier. */
    public function createFolder(Request $request)
    {
        $email = $request->session()->get('mail_user');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64', 'regex:/^[^\/\\\\\x00-\x1f]+$/'],
        ]);
        try {
            $this->mail->createFolder($email, trim($data['name']));
            return back()->with('success', 'Dossier créé.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Création impossible : ' . $e->getMessage());
        }
    }

    /** DELETE /webmail/folders — supprime un dossier libre. */
    public function deleteFolder(Request $request)
    {
        $email = $request->session()->get('mail_user');
        $data = $request->validate(['path' => 'required|string|max:128']);
        try {
            $this->mail->deleteFolder($email, $data['path']);
            return redirect('/webmail')->with('success', 'Dossier supprimé.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /** POST /webmail/move — déplace un message vers un autre dossier. */
    public function move(Request $request)
    {
        $email = $request->session()->get('mail_user');
        $data = $request->validate([
            'folder' => 'required|string|max:128',
            'uid'    => 'required|integer',
            'target' => 'required|string|max:128',
        ]);
        try {
            $this->mail->moveMessage($email, $data['folder'], (int) $data['uid'], $data['target']);
            return redirect('/webmail?folder=' . urlencode($data['folder']))
                ->with('success', 'Message déplacé.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Déplacement impossible : ' . $e->getMessage());
        }
    }

    /** POST /webmail/trash — supprime un message (Corbeille, ou définitif). */
    public function trash(Request $request)
    {
        $email = $request->session()->get('mail_user');
        $data = $request->validate([
            'folder' => 'required|string|max:128',
            'uid'    => 'required|integer',
        ]);
        try {
            $this->mail->deleteMessage($email, $data['folder'], (int) $data['uid']);
            return redirect('/webmail?folder=' . urlencode($data['folder']))
                ->with('success', 'Message supprimé.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Suppression impossible : ' . $e->getMessage());
        }
    }

    /** POST /webmail/archive — archive un message. */
    public function archive(Request $request)
    {
        $email = $request->session()->get('mail_user');
        $data = $request->validate([
            'folder' => 'required|string|max:128',
            'uid'    => 'required|integer',
        ]);
        try {
            $this->mail->archiveMessage($email, $data['folder'], (int) $data['uid']);
            return redirect('/webmail?folder=' . urlencode($data['folder']))
                ->with('success', 'Message archivé.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Archivage impossible : ' . $e->getMessage());
        }
    }

    /** POST /webmail/spam — déplace un message vers le Spam. */
    public function spam(Request $request)
    {
        $email = $request->session()->get('mail_user');
        $data = $request->validate([
            'folder' => 'required|string|max:128',
            'uid'    => 'required|integer',
        ]);
        try {
            $this->mail->spamMessage($email, $data['folder'], (int) $data['uid']);
            return redirect('/webmail?folder=' . urlencode($data['folder']))
                ->with('success', 'Message signalé comme indésirable.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Action impossible : ' . $e->getMessage());
        }
    }

    /** POST /webmail/seen — marque un message comme lu / non lu. */
    public function seen(Request $request)
    {
        $email = $request->session()->get('mail_user');
        $data = $request->validate([
            'folder' => 'required|string|max:128',
            'uid'    => 'required|integer',
            'seen'   => 'required|boolean',
        ]);
        try {
            $this->mail->setSeen($email, $data['folder'], (int) $data['uid'], (bool) $data['seen']);
            return back()->with('success', $data['seen'] ? 'Marqué comme lu.' : 'Marqué comme non lu.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Action impossible : ' . $e->getMessage());
        }
    }/** POST /webmail/snooze — masque un message jusqu'à une date. */
    public function snooze(Request $request, SnoozeService $snz)
    {
        $email = (string) $request->session()->get('mail_user');
        $data = $request->validate([
            'folder' => 'required|string|max:128',
            'uid'    => 'required|integer',
            'until'  => 'required|date',
        ]);
        $snz->snooze($email, $data['folder'], (int) $data['uid'], strtotime((string) $data['until']));
        return back()->with('success', 'En sommeil jusqu\'au ' . date('d/m/Y H:i', strtotime((string) $data['until'])));
    }

    /** POST /webmail/wake — réveille immédiatement un message snoozé. */
    public function wakeSnoozed(Request $request, SnoozeService $snz)
    {
        $email = (string) $request->session()->get('mail_user');
        $data = $request->validate(['folder' => 'required|string|max:128', 'uid' => 'required|integer']);
        $snz->wake($email, $data['folder'], (int) $data['uid']);
        return back()->with('success', 'Réveillé.');
    }/**
     * GET /webmail/raw — source RFC822 brute du mail (style Gmail « Afficher l'original »).
     * Rendu dans un mini-template HTML avec syntax-highlight des headers.
     */
    public function raw(Request $request)
    {
        $email = (string) $request->session()->get('mail_user');
        $folder = (string) $request->query('folder', 'INBOX');
        $uid    = (int) $request->query('uid', 0);
        if (! $uid) abort(400);

        $raw = $this->mail->raw($email, $folder, $uid);
        if (! $raw) abort(404);

        $size = strlen($raw);
        $sizeKb = number_format($size / 1024, 1, ',', ' ');
        // Sépare headers (avant double CRLF) et body
        [$hdrs, $body] = explode("\r\n\r\n", $raw, 2) + ['', ''];
        // Colore les noms de headers
        $hdrsHtml = preg_replace('/^([A-Za-z][\w-]+):/m', '<span style="color:#FF4D2E;font-weight:700">$1</span>:',
            htmlspecialchars($hdrs, ENT_QUOTES, 'UTF-8'));
        $bodyHtml = htmlspecialchars($body, ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="fr"><head>
<meta charset="utf-8">
<title>Original du message · Noliae Mail</title>
<style>
  * { box-sizing: border-box; }
  body { margin:0; font:14px ui-monospace,SFMono-Regular,Consolas,monospace; background:#fafaf7; color:#15151a; }
  .topbar { background:#15151a; color:#fff; padding:14px 24px; display:flex; align-items:center; gap:16px; justify-content:space-between; }
  .topbar h1 { font:600 14px -apple-system,Segoe UI,Roboto,sans-serif; margin:0; }
  .topbar .meta { font:400 12px -apple-system,Segoe UI,Roboto,sans-serif; color:#9ca3af; }
  .topbar .actions { display:flex; gap:8px; }
  .btn { background:#FF4D2E; color:#fff; padding:6px 14px; border-radius:999px; border:0; font:700 12px -apple-system,Segoe UI,Roboto,sans-serif; cursor:pointer; text-decoration:none; }
  .btn:hover { background:#df3c1f; }
  .btn-ghost { background:rgba(255,255,255,.1); color:#fff; }
  .btn-ghost:hover { background:rgba(255,255,255,.2); }
  .wrap { max-width:1100px; margin:24px auto; padding:0 24px; }
  .panel { background:#fff; border:1px solid #e5e7eb; border-radius:14px; overflow:hidden; }
  .panel-h { padding:10px 16px; border-bottom:1px solid #f1f1f3; font:700 11px -apple-system,Segoe UI,Roboto,sans-serif; color:#9ca3af; text-transform:uppercase; letter-spacing:.08em; }
  pre { margin:0; padding:18px 22px; white-space:pre-wrap; word-break:break-word; line-height:1.55; font-size:12.5px; }
  .body { color:#374151; }
</style>
</head><body>
<div class="topbar">
  <div><h1>Original du message</h1><div class="meta">{$sizeKb} Ko · format RFC 822 brut</div></div>
  <div class="actions">
    <button class="btn" onclick="navigator.clipboard.writeText(document.querySelector('#raw-text').innerText).then(()=>this.textContent='✓ Copié')">📋 Copier</button>
    <a class="btn btn-ghost" href="data:message/rfc822;charset=utf-8;base64,{$this->b64($raw)}" download="message.eml">⬇ Télécharger .eml</a>
    <button class="btn btn-ghost" onclick="window.close()">Fermer</button>
  </div>
</div>
<div class="wrap">
  <div class="panel">
    <div class="panel-h">En-têtes</div>
    <pre>{$hdrsHtml}</pre>
  </div>
  <div class="panel" style="margin-top:18px">
    <div class="panel-h">Corps brut</div>
    <pre class="body" id="raw-text">{$bodyHtml}</pre>
  </div>
</div>
</body></html>
HTML;
        return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    private function b64(string $s): string { return base64_encode($s); }

    /**
     * GET /webmail/attachment — télécharge / stream une PJ.
     * URL : /webmail/attachment?folder=…&uid=…&name=…&inline=1
     */
    public function attachment(Request $request)
    {
        $email = (string) $request->session()->get('mail_user');
        $folder = (string) $request->query('folder', 'INBOX');
        $uid    = (int) $request->query('uid', 0);
        $name   = (string) $request->query('name', '');
        $inline = (bool) $request->query('inline', false);
        if (! $uid || $name === '') abort(400);

        $att = $this->mail->attachment($email, $folder, $uid, $name);
        if (! $att) abort(404);

        $disp = $inline ? 'inline' : 'attachment';
        // Anti-header-injection : strip CR/LF + quote + chars de contrôle
        // (sinon un mail malicieux peut splitter le header HTTP via le nom de PJ).
        $safeName  = preg_replace('/[\r\n"\x00-\x1f]/', '_', (string) $att['name']) ?: 'fichier';
        $safeName  = substr($safeName, 0, 200);
        // Encodage RFC 5987 pour caractères non-ASCII (UTF-8 dans filename*=)
        $rfc5987   = "UTF-8''" . rawurlencode($safeName);
        return response($att['content'], 200, [
            'Content-Type'        => $att['mime'],
            'Content-Disposition' => $disp . '; filename="' . $safeName . '"; filename*=' . $rfc5987,
            'Cache-Control'       => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** POST /webmail/star — favori / non-favori. */
    public function star(Request $request)
    {
        $email = $request->session()->get('mail_user');
        $data = $request->validate([
            'folder' => 'required|string|max:128',
            'uid'    => 'required|integer',
            'on'     => 'required|boolean',
        ]);
        try {
            $this->mail->setStar($email, $data['folder'], (int) $data['uid'], (bool) $data['on']);
            return back()->with('success', $data['on'] ? 'Ajouté aux favoris.' : 'Retiré des favoris.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /** POST /webmail/label — couleur de label sur un message. */
    public function label(Request $request)
    {
        $email = $request->session()->get('mail_user');
        $data = $request->validate([
            'folder' => 'required|string|max:128',
            'uid'    => 'required|integer',
            'color'  => 'nullable|string|in:red,orange,yellow,green,blue,violet',
        ]);
        try {
            $this->mail->setLabel($email, $data['folder'], (int) $data['uid'], $data['color'] ?? null);
            return back()->with('success', $data['color'] ? 'Label appliqué.' : 'Label retiré.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /** POST /webmail/bulk — applique une action à plusieurs messages. */
    public function bulk(Request $request)
    {
        $email = $request->session()->get('mail_user');
        $data = $request->validate([
            'folder' => 'required|string|max:128',
            'uids'   => 'required|array|min:1|max:200',
            'uids.*' => 'integer',
            'action' => 'required|string|in:trash,archive,spam,seen,unseen,move',
            'target' => 'nullable|string|max:128',
        ]);

        $errors = 0; $count = 0;
        foreach ($data['uids'] as $uid) {
            try {
                switch ($data['action']) {
                    case 'trash':   $this->mail->deleteMessage($email, $data['folder'], (int) $uid); break;
                    case 'archive': $this->mail->archiveMessage($email, $data['folder'], (int) $uid); break;
                    case 'spam':    $this->mail->spamMessage($email, $data['folder'], (int) $uid); break;
                    case 'seen':    $this->mail->setSeen($email, $data['folder'], (int) $uid, true); break;
                    case 'unseen':  $this->mail->setSeen($email, $data['folder'], (int) $uid, false); break;
                    case 'move':
                        if (empty($data['target'])) { $errors++; break; }
                        $this->mail->moveMessage($email, $data['folder'], (int) $uid, $data['target']);
                        break;
                }
                $count++;
            } catch (\Throwable $e) {
                $errors++;
            }
        }

        $msg = $count . ' message' . ($count > 1 ? 's' : '') . ' traité' . ($count > 1 ? 's' : '');
        if ($errors > 0) $msg .= ' (' . $errors . ' échec' . ($errors > 1 ? 's' : '') . ')';
        return redirect('/webmail?folder=' . urlencode($data['folder']))
            ->with($errors && !$count ? 'error' : 'success', $msg . '.');
    }

    /**
     * GET /webmail/img — proxy d'images pour les mails.
     * Bloque les pixels traceurs : le serveur fait la requête à la place
     * du client, sans cookies ni referrer, et refuse les IPs privées.
     */
    public function img(Request $request)
    {
        $url = (string) $request->query('u', '');
        $sig = (string) $request->query('s', '');
        $expected = hash_hmac('sha256', $url, (string) config('app.key'));
        if (! hash_equals($expected, $sig)) {
            abort(403);
        }

        // Cache Redis (clé déterministe sur l'URL signée).
        $cacheKey = 'noliae:img:' . substr($sig, 0, 32);
        $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
        if ($cached && ! empty($cached['body'])) {
            return response($cached['body'], 200, [
                'Content-Type'            => $cached['ct'],
                'Content-Length'          => strlen($cached['body']),
                'Cache-Control'           => 'public, max-age=604800, immutable',
                'X-Content-Type-Options'  => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'",
                'Referrer-Policy'         => 'no-referrer',
                'CDN-Cache-Control'       => 'public, max-age=2592000', // CF / Fastly : 30 j
                'X-Noliae-Cache'          => 'HIT',
            ]);
        }

        $parts = parse_url($url);
        if (! $parts || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            abort(400);
        }
        $host = strtolower($parts['host'] ?? '');
        if ($host === '' || $host === 'localhost') {
            abort(400);
        }
        // Anti-SSRF : on résout l'hôte et on refuse les IPs privées / réservées.
        $ips = @gethostbynamel($host) ?: [];
        if (! $ips) {
            abort(400);
        }
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP,
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                abort(403);
            }
        }

        // Anti DNS-rebind : on FIGE l'IP résolue ci-dessus pour empêcher
        // un attaquant de renvoyer 169.254.169.254 entre le check et le curl.
        $port  = (int) ($parts['port'] ?? (($parts['scheme'] ?? 'http') === 'https' ? 443 : 80));
        $ch    = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_USERAGENT      => 'NoliaeMail-Image-Proxy/1.0',
            CURLOPT_HEADER         => false,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_MAXFILESIZE    => 8 * 1024 * 1024,
            // Pin l'IP validée pour neutraliser le DNS-rebind TOCTOU.
            CURLOPT_RESOLVE        => ["$host:$port:" . $ips[0]],
        ]);
        $body = curl_exec($ch);
        $ct   = (string) (curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'application/octet-stream');
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($body === false || $code < 200 || $code >= 300) {
            abort(502);
        }
        if (stripos($ct, 'image/') !== 0) {
            abort(415);
        }

        // Stockage Redis 7 jours (limite 8 Mo de toute façon)
        \Illuminate\Support\Facades\Cache::put($cacheKey,
            ['body' => $body, 'ct' => $ct],
            now()->addDays(7)
        );

        return response($body, 200, [
            'Content-Type'            => $ct,
            'Content-Length'          => strlen($body),
            'Cache-Control'           => 'public, max-age=604800, immutable',
            'X-Content-Type-Options'  => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
            'Referrer-Policy'         => 'no-referrer',
            'CDN-Cache-Control'       => 'public, max-age=2592000',
            'X-Noliae-Cache'          => 'MISS',
        ]);
    }/**
     * POST /webmail/pgp/decrypt — déchiffre un mail PGP côté serveur Noliae.
     * La clé privée est extraite des settings utilisateur, jamais persistée
     * hors du keyring jetable. La passphrase est fournie par requête et
     * n'est pas mémorisée.
     */
    public function pgpDecrypt(Request $request, SettingsService $settingsSvc, PgpService $gpg)
    {
        $email = (string) $request->session()->get('mail_user');
        $data = $request->validate([
            'cipher'     => 'required|string|max:1000000',
            'passphrase' => 'required|string|max:200',
        ]);
        $settings = $settingsSvc->get($email);
        if (empty($settings['pgp_private_key'])) {
            return response()->json(['error' => "Aucune clé privée PGP enregistrée."], 422);
        }
        try {
            $plain = $gpg->decrypt(
                (string) $settings['pgp_private_key'],
                $data['passphrase'],
                $data['cipher']
            );
            return response()->json(['plain' => $plain]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /webmail/pgp/sign — signe (cleartext) un message avec la clé privée
     * stockée dans les settings, server-side.
     */
    public function pgpSign(Request $request, SettingsService $sv, PgpService $gpg)
    {
        $email = (string) $request->session()->get('mail_user');
        $data = $request->validate([
            'text'       => 'required|string|max:1000000',
            'passphrase' => 'required|string|max:200',
        ]);
        $s = $sv->get($email);
        if (empty($s['pgp_private_key'])) {
            return response()->json(['error' => "Aucune clé PGP enregistrée."], 422);
        }
        try {
            $signed = $gpg->clearsign($s['pgp_private_key'], $data['passphrase'], $data['text']);
            return response()->json(['signed' => $signed]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /** GET /webmail/pgp/contacts — liste mes contacts PGP. */
    public function pgpContacts(Request $request, KeyStoreService $store)
    {
        $email = (string) $request->session()->get('mail_user');
        return response()->json(['contacts' => array_values($store->all($email))]);
    }

    /** POST /webmail/pgp/import — ajoute une clé publique au store. */
    public function pgpImport(Request $request, KeyStoreService $store)
    {
        $email = (string) $request->session()->get('mail_user');
        $data = $request->validate([
            'email'   => 'required|email',
            'armored' => 'required|string|max:200000',
            'source'  => 'nullable|string|in:manual,keyserver,inbound-mail',
        ]);
        if (! str_contains($data['armored'], 'BEGIN PGP PUBLIC KEY BLOCK')) {
            return response()->json(['error' => "Bloc PGP PUBLIC KEY BLOCK introuvable."], 422);
        }
        $entry = $store->save($email, $data['email'], $data['armored'], $data['source'] ?? 'manual');
        return response()->json($entry);
    }

    /** DELETE /webmail/pgp/import — retire une clé du store. */
    public function pgpForget(Request $request, KeyStoreService $store)
    {
        $email = (string) $request->session()->get('mail_user');
        $data = $request->validate(['email' => 'required|email']);
        $store->delete($email, $data['email']);
        return response()->json(['ok' => true]);
    }

    /**
     * GET /webmail/pgp/lookup?email=x — cherche sur keys.openpgp.org.
     * Si trouvée, on l'ajoute automatiquement au store local.
     */
    public function pgpLookup(Request $request, KeyStoreService $store)
    {
        $owner = (string) $request->session()->get('mail_user');
        $email = strtolower(trim((string) $request->query('email', '')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) abort(400);

        $url = 'https://keys.openpgp.org/vks/v1/by-email/' . rawurlencode($email);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            // SSRF mitigation : pas de follow auto (sinon redirect 302 vers
            // un endpoint cloud-metadata possible). On fait le suivi
            // explicitement avec validation d'IP si besoin.
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_USERAGENT      => 'NoliaeMail-KeyLookup/1.0',
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($code !== 200 || ! is_string($body) || ! str_contains($body, 'BEGIN PGP PUBLIC KEY BLOCK')) {
            return response()->json(['found' => false], 404);
        }
        $entry = $store->save($owner, $email, $body, 'keyserver');
        return response()->json(['found' => true, 'entry' => $entry]);
    }

    /**
     * GET /webmail/pgp/{hash}.asc — annuaire de clés publiques PGP.
     * URL : https://mail.noliae.com/webmail/pgp/{sha256(email)}.asc
     * (publique, sans auth — permet à un autre utilisateur d'encrypter)
     */
    public function pgpPublicKey(string $hash, SettingsService $svc)
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $hash)) {
            abort(404);
        }
        // Scan local des fichiers settings (petit nombre d'utilisateurs)
        $dir = storage_path('app/webmail-settings');
        if (! is_dir($dir)) abort(404);
        foreach (scandir($dir) as $f) {
            if (! str_ends_with($f, '.json')) continue;
            try {
                $data = json_decode(file_get_contents("$dir/$f"), true) ?: [];
                if (! empty($data['pgp_public_key']) && ! empty($data['_email'] ?? '')) {
                    if (hash('sha256', strtolower(trim($data['_email']))) === $hash) {
                        return response($data['pgp_public_key'], 200, [
                            'Content-Type'  => 'application/pgp-keys',
                            'Cache-Control' => 'public, max-age=3600',
                        ]);
                    }
                }
            } catch (\Throwable $e) {}
        }
        abort(404);
    }

    /** POST /webmail/settings — enregistre les réglages utilisateur. */
    public function saveSettings(Request $request, SettingsService $svc)
    {
        $email = $request->session()->get('mail_user');
        $data = $request->validate([
            'signature_html'    => 'nullable|string|max:50000',
            'ask_receipts'      => 'nullable|boolean',
            'pgp_public_key'    => 'nullable|string|max:50000',
            'pgp_private_key'   => 'nullable|string|max:200000',
            'pgp_fingerprint'   => 'nullable|string|max:64',
            'pgp_attach_public' => 'nullable|boolean',
            'view_mode'         => 'nullable|string|in:list,thread',
            'rules'             => 'nullable|array|max:30',
            'rules.*.id'        => 'nullable|string|max:36',
            'rules.*.name'      => 'nullable|string|max:80',
            'rules.*.match.from'    => 'nullable|string|max:200',
            'rules.*.match.subject' => 'nullable|string|max:200',
            'rules.*.action.color'  => 'nullable|string|in:red,orange,yellow,green,blue,violet',
            'rules.*.action.star'   => 'nullable|boolean',
            'aliases'           => 'nullable|array|max:10',
            'aliases.*'         => 'string|email|max:200',
            'vacation'          => 'nullable|array',
            'vacation.enabled'  => 'nullable|boolean',
            'vacation.message'  => 'nullable|string|max:2000',
            'vacation.from'     => 'nullable|date',
            'vacation.to'       => 'nullable|date',
        ]);
        $out = $svc->save((string) $email, $data);
        return response()->json($out);
    }

    /**
     * POST /webmail/receipt — envoie un MDN (accusé de réception) en réponse
     * à un mail dont le header Disposition-Notification-To était présent.
     */
    public function sendReceipt(Request $request, MailQueue $queue)
    {
        $email = $request->session()->get('mail_user');
        $name  = (string) $request->session()->get('mail_name', '');
        $data = $request->validate([
            'to'         => 'required|email',
            'subject'    => 'nullable|string|max:255',
            'message_id' => 'nullable|string|max:512',
        ]);
        try {
            $queue->publish([
                'from'        => $email,
                'from_name'   => $name,
                'to'          => [$data['to']],
                'cc'          => [],
                'subject'     => 'Read: ' . ($data['subject'] ?? '(sans objet)'),
                'html'        => '<p>Votre message <em>' . htmlspecialchars($data['subject'] ?? '', ENT_QUOTES) . '</em> a été lu par ' . htmlspecialchars($email, ENT_QUOTES) . '.</p>',
                'in_reply_to' => $data['message_id'] ?? null,
            ]);
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }

    /**
     * POST /webmail/draft — enregistre / met à jour un brouillon IMAP.
     * Body : {to, cc, subject, body, in_reply_to, prev_uid?} → {uid}
     */
    public function saveDraft(Request $request)
    {
        $email = $request->session()->get('mail_user');
        $name  = (string) $request->session()->get('mail_name', '');

        $data = $request->validate([
            'to'          => 'nullable|string|max:2000',
            'cc'          => 'nullable|string|max:2000',
            'subject'     => 'nullable|string|max:255',
            'body'        => 'nullable|string|max:1000000',
            'in_reply_to' => 'nullable|string|max:512',
            'prev_uid'    => 'nullable|integer',
        ]);

        $split = fn ($s) => array_values(array_filter(array_map(
            'trim', preg_split('/[,;]+/', (string) $s))));

        try {
            $uid = $this->mail->saveDraft($email, $name, [
                'to'          => $split($data['to'] ?? ''),
                'cc'          => $split($data['cc'] ?? ''),
                'subject'     => $data['subject'] ?? '',
                'html'        => $data['body'] ?? '',
                'in_reply_to' => $data['in_reply_to'] ?? null,
            ], $data['prev_uid'] ?? null);
            return response()->json(['uid' => $uid, 'saved_at' => now()->toIso8601String()]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }

    /**
     * POST /webmail/inline-image — upload d'une image à insérer dans le corps
     * du mail. Renvoie l'URL signée S3 (7 jours) à coller dans <img src=…>.
     */
    public function inlineImage(Request $request, AttachmentStore $store)
    {
        $email = $request->session()->get('mail_user');
        // On vérifie le type via le MIME réel du fichier — plus tolérant
        // que `mimes:` qui dépend de l'extension du fichier client.
        $request->validate([
            'image' => 'required|file|mimetypes:image/jpeg,image/png,image/gif,image/webp|max:10240',
        ], [
            'image.max'       => 'Image trop volumineuse (10 Mo max).',
            'image.mimetypes' => 'Format d\'image non supporté.',
        ]);
        try {
            $info = $store->upload($request->file('image'), (string) $email);
            return response()->json([
                'url'  => $info['url'],
                'name' => $info['name'],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }

    /**
     * POST /webmail/send — met le message dans la file d'envoi RabbitMQ.
     *
     * Le webmail répond instantanément ; le worker prend le relais pour
     * l'envoi SMTP + la copie IMAP dans « Envoyés ».
     */
    public function send(Request $request, AttachmentStore $store, MailQueue $queue)
    {
        $email = $request->session()->get('mail_user');

        $data = $request->validate([
            'to'             => 'required|string|max:2000',
            'cc'             => 'nullable|string|max:2000',
            'subject'        => 'nullable|string|max:255',
            'body'           => 'nullable|string|max:1000000',
            'in_reply_to'    => 'nullable|string|max:512',
            'storage'        => 'nullable|string|in:smtp,s3',
            'ttl_days'       => 'nullable|integer|min:1|max:7',
            'ask_receipt'    => 'nullable|boolean',
            'pgp_encrypted'  => 'nullable|boolean',
            'attachments'    => 'nullable|array|max:20',
            'attachments.*'  => 'file|max:1048576', // 1 Go / fichier (cap S3)
            'draft_uid'      => 'nullable|integer',
            'send_at'        => 'nullable|date', // ISO 8601 pour envoi planifié
            'from_alias'     => 'nullable|email|max:200',
        ]);

        $storage = $data['storage'] ?? 'smtp';
        $ttlDays = (int) ($data['ttl_days'] ?? 7);
        $files   = $request->file('attachments', []);
        $totalSize = 0;
        foreach ($files as $f) { if ($f && $f->isValid()) $totalSize += (int) $f->getSize(); }

        if ($storage === 'smtp' && $totalSize > 10 * 1024 * 1024) {
            return back()->with('error', 'Mode email : le total des pièces jointes doit faire 10 Mo maximum. Passe en mode « Stockage Noliae » pour des fichiers plus lourds.');
        }
        if ($storage === 's3' && $totalSize > 1024 * 1024 * 1024) {
            return back()->with('error', 'Stockage Noliae : 1 Go maximum au total par envoi.');
        }

        $split = fn ($s) => array_values(array_filter(array_map(
            'trim', preg_split('/[,;]+/', (string) $s))));

        $to = $split($data['to']);
        if (empty($to)) {
            return back()->with('error', 'Indiquez au moins un destinataire.');
        }

        try {
            // Alias autorisé ? (déclaré dans les settings)
            $settingsCheck = app(SettingsService::class)->get((string) $email);
            $aliasesAllowed = array_merge([$email], (array) ($settingsCheck['aliases'] ?? []));
            $fromAddr = ! empty($data['from_alias']) && in_array($data['from_alias'], $aliasesAllowed, true)
                ? $data['from_alias'] : $email;

            $payload = [
                'from'        => $fromAddr,
                'from_name'   => (string) $request->session()->get('mail_name', ''),
                'to'          => $to,
                'cc'          => $split($data['cc'] ?? ''),
                'subject'     => $data['subject'] ?? '',
                'html'        => $data['body'] ?? '',
                'in_reply_to' => $data['in_reply_to'] ?? null,
                'ask_receipt' => (bool) ($data['ask_receipt'] ?? false),
                'pgp_encrypted' => (bool) ($data['pgp_encrypted'] ?? false),
            ];

            // Réglages utilisateur : auto-attache de la clé PGP publique
            $settings = app(SettingsService::class)->get((string) $email);
            if (! empty($settings['pgp_attach_public']) && ! empty($settings['pgp_public_key'])) {
                $payload['pgp_public_key']    = $settings['pgp_public_key'];
                $payload['pgp_attach_public'] = true;
            }

            $s3Links = [];
            if ($storage === 's3') {
                foreach ($files as $file) {
                    if ($file && $file->isValid()) {
                        $s3Links[] = $store->upload($file, $email, $ttlDays);
                    }
                }
                $payload['s3_links'] = $s3Links;
            } else {
                $inline = [];
                foreach ($files as $file) {
                    if ($file && $file->isValid()) {
                        $inline[] = [
                            'name' => $file->getClientOriginalName() ?: 'fichier',
                            'mime' => $file->getMimeType() ?: 'application/octet-stream',
                            'b64'  => base64_encode(file_get_contents($file->getRealPath())),
                        ];
                    }
                }
                $payload['attachments_inline'] = $inline;
            }

            // Envoi planifié → file Redis ZSET, sinon envoi immédiat RabbitMQ
            if (! empty($data['send_at'])) {
                $epoch = strtotime((string) $data['send_at']);
                if ($epoch > time()) {
                    app(ScheduleService::class)->enqueue($payload, $epoch);
                    return back()->with('success', 'Message planifié pour le ' . date('d/m/Y H:i', $epoch) . '.');
                }
            }
            $queue->publish($payload);

            // Si l'envoi vient d'un brouillon, on le retire de Brouillons.
            if (! empty($data['draft_uid'])) {
                try {
                    $client = null;
                    // Trouver le dossier Drafts et supprimer le message
                    $box = null;
                    foreach ($this->mail->folders($email) as $f) {
                        if (($f['rank'] ?? 99) === 1) { $draftPath = $f['path']; break; }
                    }
                    if (! empty($draftPath)) {
                        $this->mail->deleteMessage($email, $draftPath, (int) $data['draft_uid']);
                    }
                } catch (\Throwable $e) {}
            }

            $msg = 'Message en file d\'envoi.';
            if ($storage === 's3' && $s3Links) {
                $msg = count($s3Links) . ' fichier(s) sur S3 (lien ' . $ttlDays . ' j) — en file.';
            } elseif ($storage === 'smtp' && count($files)) {
                $msg = count($files) . ' pièce(s) jointe email — en file.';
            }
            return back()->with('success', $msg);
        } catch (\Throwable $e) {
            return back()->with('error', "Mise en file impossible : " . $e->getMessage());
        }
    }
}
