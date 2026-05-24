<?php

namespace App\Services;

use App\Services\MailQueue;
use Webklex\PHPIMAP\ClientManager;

/**
 * Accès IMAP au serveur mail Noliae (Dovecot, 149) via le master-user.
 */
class MailboxService
{
    /** Parse les opérateurs de recherche Gmail-like. */
    private function parseSearchOperators(string $q): array
    {
        $out = ['text' => ''];
        $rest = $q;
        $ops = ['from', 'to', 'subject', 'before', 'after', 'is', 'has'];
        foreach ($ops as $op) {
            if (preg_match_all('/\\b' . $op . ':("([^"]+)"|(\\S+))/i', $rest, $mm)) {
                foreach ($mm[2] as $i => $val) {
                    $v = $val !== '' ? $val : $mm[3][$i];
                    if ($op === 'is') {
                        if (strcasecmp($v, 'unread') === 0)  $out['is_unread'] = true;
                        if (strcasecmp($v, 'starred') === 0) $out['is_starred'] = true;
                        if (strcasecmp($v, 'read') === 0)    $out['is_read'] = true;
                    } elseif ($op === 'has') {
                        if (strcasecmp($v, 'attachment') === 0) $out['has_attachment'] = true;
                    } else {
                        $out[$op] = $v;
                    }
                }
                $rest = preg_replace('/\\b' . $op . ':("[^"]+"|\\S+)/i', '', $rest);
            }
        }
        $out['text'] = trim(preg_replace('/\s+/', ' ', $rest));
        return $out;
    }

    private function client(string $email)
    {
        $cm = new ClientManager();
        $client = $cm->make([
            'host'          => config('mail.imap_host'),
            'port'          => config('mail.imap_port'),
            // Configurable via MAIL_IMAP_ENCRYPTION : "ssl" / "tls" / "starttls" / "" (plain).
            // Démo OSS = plain car Dovecot interne sur réseau privé docker.
            'encryption'    => config('mail.imap_encryption') ?: false,
            'validate_cert' => (bool) config('mail.imap_validate_cert', false),
            'protocol'      => 'imap',
            'username'      => $email . '*' . config('mail.master_user'),
            'password'      => config('mail.master_pass'),
        ]);
        $client->connect();
        return $client;
    }

    /**
     * Génère l'URL signée du proxy d'image pour une URL distante.
     * Le proxy refait la requête côté serveur, sans cookies ni referrer :
     * les pixels traceurs ne voient jamais l'IP ni le client du destinataire.
     */
    public static function proxyUrl(string $url): string
    {
        $key = (string) config('app.key');
        $sig = hash_hmac('sha256', $url, $key);
        return '/webmail/img?u=' . rawurlencode($url) . '&s=' . $sig;
    }

    /**
     * Réécrit le HTML d'un mail :
     *   - proxifie toutes les images externes (anti-tracker)
     *   - extrait UNIQUEMENT le body via DOMDocument
     *   - rewrap dans un <!DOCTYPE>+<head> propre avec CSP stricte
     *   - sanitise les attributs dangereux (onXxx, javascript:, …)
     */
    public static function rewriteMailHtml(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        // 1. Pré-rewrite des <img> avant parsing DOMDocument (plus simple en regex)
        $html = preg_replace_callback(
            '/<img\b([^>]*?)\bsrc\s*=\s*(["\'])([^"\']+)\2([^>]*)>/i',
            function ($m) {
                $url = trim(html_entity_decode($m[3], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($url === '' || stripos($url, 'cid:') === 0 || stripos($url, 'data:') === 0) return $m[0];
                if (! preg_match('#^https?://#i', $url)) return $m[0];
                return '<img' . $m[1] . 'src="' . htmlspecialchars(self::proxyUrl($url), ENT_QUOTES) . '"'
                    . ' referrerpolicy="no-referrer" loading="lazy" decoding="async"' . $m[4] . '>';
            },
            $html
        );

        // 2. srcset → out
        $html = preg_replace('/\ssrcset\s*=\s*"[^"]*"/i', '', $html);
        $html = preg_replace("/\ssrcset\s*=\s*'[^']*'/i", '', $html);

        // 3. Parse en document HTML complet (DOMDocument ajoute auto html/body si absents)
        $bodyHtml = $html;
        if (trim($html) !== '') {
            $doc = new \DOMDocument();
            libxml_use_internal_errors(true);
            // mb_convert pour forcer UTF-8 (DOMDocument suppose ISO-8859-1 sinon)
            $src = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>';
            @$doc->loadHTML($src, LIBXML_NOERROR | LIBXML_NOWARNING);
            libxml_clear_errors();

            $xp = new \DOMXPath($doc);

            // 3.a Retire tous les nœuds dangereux (où qu'ils soient)
            foreach (iterator_to_array($xp->query('//script|//iframe|//object|//embed|//form|//base|//meta|//link')) as $node) {
                $node->parentNode?->removeChild($node);
            }

            // 3.b Nettoie les attributs dangereux
            $jsUriRe = '/^\s*(javascript|vbscript|data:text\/html)/i';
            foreach ($xp->query('//*') as $el) {
                $toRemove = [];
                foreach ($el->attributes as $attr) {
                    $name = strtolower($attr->name);
                    if (str_starts_with($name, 'on')) { $toRemove[] = $name; continue; }
                    if (in_array($name, ['href','src','action','formaction','background'], true)
                        && preg_match($jsUriRe, $attr->value)) { $toRemove[] = $name; continue; }
                    if ($name === 'style' && preg_match('/expression\(|behavior\s*:|javascript:/i', $attr->value)) {
                        $toRemove[] = $name;
                    }
                }
                foreach ($toRemove as $a) $el->removeAttribute($a);
            }

            // 3.c Extrait UNIQUEMENT le contenu du <body>
            $body = $doc->getElementsByTagName('body')->item(0);
            $bodyHtml = '';
            if ($body) {
                foreach ($body->childNodes as $child) {
                    $bodyHtml .= $doc->saveHTML($child);
                }
            }
        }

        // 4. Rewrap dans un document propre avec CSP en TOUT PREMIER dans le <head>.
        $csp = '<meta http-equiv="Content-Security-Policy" '
            . 'content="default-src \'none\'; img-src \'self\' data:; '
            . 'style-src \'unsafe-inline\'; font-src data:; base-uri \'none\'">';
        return '<!DOCTYPE html><html><head><meta charset="utf-8">' . $csp
             . '<style>body{margin:0;font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif}</style>'
             . '</head><body>' . $bodyHtml . '</body></html>';
    }

    /** Date d'un message → ISO 8601, ou null. */
    private function dateOf($m): ?string
    {
        try {
            $d = $m->getDate();
            if (! $d) {
                return null;
            }
            if (method_exists($d, 'toDate')) {
                $c = $d->toDate();
                if ($c) {
                    return $c->toIso8601String();
                }
            }
            $s = trim((string) $d);
            return $s !== '' ? $s : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Adresse expéditeur → ['name'=>, 'mail'=>]. */
    private function addr($attr): array
    {
        try {
            $a = is_object($attr) && method_exists($attr, 'first') ? $attr->first() : null;
            if ($a) {
                $name = $a->personal ?: $a->mail;
                return ['name' => $this->mimeDecode((string) $name), 'mail' => (string) $a->mail];
            }
        } catch (\Throwable $e) {
        }
        return ['name' => '', 'mail' => ''];
    }

    /**
     * Décode un header MIME RFC 2047 (=?UTF-8?Q?...?= ou =?...?B?...?=).
     * Tolère les chaînes déjà décodées et les encodages exotiques.
     */
    private function mimeDecode(string $s): string
    {
        if ($s === '' || strpos($s, '=?') === false) return $s;
        if (function_exists('iconv_mime_decode')) {
            $out = @iconv_mime_decode($s, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
            if ($out !== false && $out !== '') return $out;
        }
        if (function_exists('mb_decode_mimeheader')) {
            $prev = mb_internal_encoding();
            mb_internal_encoding('UTF-8');
            $out = @mb_decode_mimeheader($s);
            mb_internal_encoding($prev);
            if ($out !== '') return $out;
        }
        return $s;
    }

    private function flagSeen($m): bool
    {
        try {
            foreach ((array) $m->getFlags()->all() as $f) {
                if (stripos((string) $f, 'seen') !== false) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
        }
        return false;
    }

    /** Extrait l'état starred et la couleur de label d'un message. */
    private function flagExtras($m): array
    {
        $starred = false;
        $label = null;
        try {
            foreach ((array) $m->getFlags()->all() as $f) {
                $s = (string) $f;
                if (stripos($s, 'flagged') !== false) {
                    $starred = true;
                }
                if (preg_match('/\\$?Noliae-(red|orange|yellow|green|blue|violet)/i', $s, $mm)) {
                    $label = strtolower($mm[1]);
                }
            }
        } catch (\Throwable $e) {}
        return ['starred' => $starred, 'label' => $label];
    }

    /** Rang d'affichage : INBOX = 0, dossiers spéciaux 1-5, dossiers libres 10. */
    private function folderRank(string $name): int
    {
        $map = [
            'inbox' => 0,
            'drafts' => 1, 'brouillons' => 1,
            'sent' => 2, 'sent messages' => 2, 'envoyés' => 2, 'envoyes' => 2,
            'archive' => 3, 'archives' => 3,
            'junk' => 4, 'spam' => 4,
            'trash' => 5, 'corbeille' => 5, 'deleted' => 5,
        ];
        return $map[mb_strtolower($name)] ?? 10;
    }

    public function folders(string $email): array
    {
        $client = $this->client($email);
        $out = [];
        foreach ($client->getFolders(false) as $folder) {
            $rank = $this->folderRank($folder->name);
            $out[] = [
                'name'   => $folder->name,
                'path'   => $folder->path,
                'rank'   => $rank,
                'locked' => $rank < 10, // INBOX + dossiers spéciaux : non supprimables
            ];
        }
        $client->disconnect();
        // INBOX toujours en tête, puis dossiers spéciaux, puis dossiers libres (alpha).
        usort($out, fn ($a, $b) => ($a['rank'] <=> $b['rank']) ?: strcasecmp($a['name'], $b['name']));
        return $out;
    }

    /** Crée un dossier. */
    public function createFolder(string $email, string $name): void
    {
        $client = $this->client($email);
        $client->createFolder($name);
        $client->disconnect();
    }

    /** Supprime un dossier (interdit pour INBOX et les dossiers spéciaux). */
    public function deleteFolder(string $email, string $path): void
    {
        if ($this->folderRank($path) < 10) {
            throw new \RuntimeException('Ce dossier est verrouillé et ne peut pas être supprimé.');
        }
        $client = $this->client($email);
        $folder = $client->getFolder($path);
        if ($folder) {
            $folder->delete();
        }
        $client->disconnect();
    }

    /** Déplace un message d'un dossier vers un autre. */
    public function moveMessage(string $email, string $folder, int $uid, string $target): void
    {
        $client = $this->client($email);
        $box = $client->getFolder($folder);
        $m = $box->query()->getMessageByUid($uid);
        if ($m) {
            $m->move($target);
        }
        $client->disconnect();
    }

    /** Trouve (ou crée) le chemin d'un dossier spécial par son rang. */
    private function specialFolderPath($client, int $rank, array $createNames = []): ?string
    {
        foreach ($client->getFolders(false) as $folder) {
            if ($this->folderRank($folder->name) === $rank) {
                return $folder->path;
            }
        }
        if ($createNames) {
            $client->createFolder($createNames[0], false);
            foreach ($client->getFolders(false) as $folder) {
                if (strcasecmp($folder->name, $createNames[0]) === 0) {
                    return $folder->path;
                }
            }
        }
        return null;
    }

    /**
     * Supprime un message : déplacement vers la Corbeille,
     * ou suppression définitive s'il y est déjà.
     */
    public function deleteMessage(string $email, string $folder, int $uid): void
    {
        $client = $this->client($email);
        $box = $client->getFolder($folder);
        $m = $box->query()->getMessageByUid($uid);
        if ($m) {
            if ($this->folderRank($folder) === 5) {
                $m->delete(); // déjà dans la Corbeille → définitif
                try { $box->expunge(); } catch (\Throwable $e) {}
            } else {
                $trash = $this->specialFolderPath($client, 5, ['Trash']);
                if ($trash) {
                    $m->move($trash);
                } else {
                    $m->delete();
                }
            }
        }
        $client->disconnect();
    }

    /** Archive un message (dossier Archives, créé au besoin). */
    public function archiveMessage(string $email, string $folder, int $uid): void
    {
        $client = $this->client($email);
        $box = $client->getFolder($folder);
        $m = $box->query()->getMessageByUid($uid);
        if ($m) {
            $target = $this->specialFolderPath($client, 3, ['Archive']);
            if ($target) {
                $m->move($target);
            }
        }
        $client->disconnect();
    }

    /** Signale un message comme indésirable (dossier Spam). */
    public function spamMessage(string $email, string $folder, int $uid): void
    {
        $client = $this->client($email);
        $box = $client->getFolder($folder);
        $m = $box->query()->getMessageByUid($uid);
        if ($m) {
            $target = $this->specialFolderPath($client, 4, ['Junk']);
            if ($target) {
                $m->move($target);
            }
        }
        $client->disconnect();
    }

    /** Marque / démarque un message comme favori (IMAP \Flagged). */
    public function setStar(string $email, string $folder, int $uid, bool $on): void
    {
        $client = $this->client($email);
        $box = $client->getFolder($folder);
        $m = $box->query()->getMessageByUid($uid);
        if ($m) {
            try { $on ? $m->setFlag('Flagged') : $m->unsetFlag('Flagged'); } catch (\Throwable $e) {}
        }
        $client->disconnect();
    }

    /**
     * Couleur de label utilisateur (keyword IMAP $Noliae-<color>).
     * $color = null retire tout label.
     */
    public function setLabel(string $email, string $folder, int $uid, ?string $color): void
    {
        $allowed = ['red', 'orange', 'yellow', 'green', 'blue', 'violet'];
        $client = $this->client($email);
        $box = $client->getFolder($folder);
        $m = $box->query()->getMessageByUid($uid);
        if ($m) {
            foreach ($allowed as $c) {
                try { $m->unsetFlag('$Noliae-' . $c); } catch (\Throwable $e) {}
            }
            if ($color && in_array($color, $allowed, true)) {
                try { $m->setFlag('$Noliae-' . $color); } catch (\Throwable $e) {}
            }
        }
        $client->disconnect();
    }

    /** Marque un message comme lu / non lu. */
    public function setSeen(string $email, string $folder, int $uid, bool $seen): void
    {
        $client = $this->client($email);
        $box = $client->getFolder($folder);
        $m = $box->query()->getMessageByUid($uid);
        if ($m) {
            try {
                $seen ? $m->setFlag('Seen') : $m->unsetFlag('Seen');
            } catch (\Throwable $e) {}
        }
        $client->disconnect();
    }

    /**
     * @param  string  $search  termes IMAP TEXT (vide = tout)
     * @return array {messages: [], total: int}
     */
    public function messages(string $email, string $folder, int $limit = 50, string $search = '', int $page = 1): array
    {
        $client = $this->client($email);
        $box = $client->getFolder($folder);
        $total = 0;
        try { $total = (int) $box->countMessages(); } catch (\Throwable $e) {}

        $q = $box->query();
        $search = trim($search);
        if ($search !== '') {
            // Parse les opérateurs façon Gmail : from:x has:attachment before:YYYY-MM-DD …
            $ops = $this->parseSearchOperators($search);
            try {
                if (! empty($ops['from']))      $q = $q->from($ops['from']);
                if (! empty($ops['to']))        $q = $q->to($ops['to']);
                if (! empty($ops['subject']))   $q = $q->subject($ops['subject']);
                if (! empty($ops['before']))    $q = $q->before(date('d-M-Y', strtotime($ops['before'])));
                if (! empty($ops['after']))     $q = $q->since(date('d-M-Y', strtotime($ops['after'])));
                if (! empty($ops['is_unread'])) $q = $q->unseen();
                if (! empty($ops['is_starred']))$q = $q->flagged();
                if (! empty($ops['text']))      $q = $q->text($ops['text']);
                if (empty($ops['from']) && empty($ops['subject']) && empty($ops['text'])
                    && empty($ops['before']) && empty($ops['after']) && empty($ops['to'])
                    && empty($ops['is_unread']) && empty($ops['is_starred'])) {
                    $q = $box->query()->text($search);
                }
            } catch (\Throwable $e) {
                try { $q = $box->query()->where('TEXT', $search); } catch (\Throwable $e2) {}
            }
        } else {
            // webklex/php-imap : query() vide = ALL côté serveur ; on ne
            // chaîne pas ->all() car selon la version cela renvoie void.
            try { $q = $q->all(); } catch (\Throwable $e) { $q = $box->query(); }
        }
        // Pagination : webklex/php-imap supporte limit($per_page, $page) en 2 args.
        $page = max(1, $page);
        $limit = max(1, min(200, $limit));
        $messages = $q->limit($limit, $page)->setFetchOrder('desc')->setFetchBody(false)->get();

        $out = [];
        foreach ($messages as $m) {
            $from = $this->addr($m->getFrom());
            $extras = $this->flagExtras($m);
            $hasAtt = false;
            try { $hasAtt = ($m->getAttachments()->count() ?? 0) > 0; } catch (\Throwable $e) {}

            // Signaux de catégorisation extraits des headers IMAP
            $listUnsub = false; $precBulk = false; $autoSub = false;
            try {
                $listUnsub = trim((string) $m->getHeader('list_unsubscribe')) !== '';
                $precBulk  = strcasecmp(trim((string) $m->getHeader('precedence')), 'bulk') === 0;
                $autoSub   = stripos(trim((string) $m->getHeader('auto_submitted')), 'no') === false
                          && trim((string) $m->getHeader('auto_submitted')) !== '';
            } catch (\Throwable $e) {}

            $out[] = [
                'uid'       => (int) $m->getUid(),
                'subject'   => $this->mimeDecode((string) $m->getSubject()) ?: '(sans objet)',
                'from'      => $from['name'],
                'from_mail' => $from['mail'],
                'from_hash' => $from['mail'] ? md5(strtolower(trim($from['mail']))) : '',
                'date'      => $this->dateOf($m),
                'seen'      => $this->flagSeen($m),
                'starred'   => $extras['starred'],
                'label'     => $extras['label'],
                'has_att'   => $hasAtt,
                'list_unsub'   => $listUnsub,
                'precedence_bulk' => $precBulk,
                'auto_submitted' => $autoSub,
            ];
        }
        $client->disconnect();

        usort($out, fn ($a, $b) => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? '')));

        return ['messages' => $out, 'total' => $total];
    }

    public function message(string $email, string $folder, int $uid): ?array
    {
        $client = $this->client($email);
        $box = $client->getFolder($folder);
        $m = $box->query()->getMessageByUid($uid);
        if (! $m) {
            $client->disconnect();
            return null;
        }
        $from = $this->addr($m->getFrom());

        $to = [];
        try {
            foreach ((array) $m->getTo()->all() as $a) {
                if (is_object($a) && $a->mail) {
                    $to[] = (string) $a->mail;
                }
            }
        } catch (\Throwable $e) {
        }

        $attachments = [];
        $pgpKeyImported = false;
        try {
            foreach ($m->getAttachments() as $a) {
                $name = (string) $a->name;
                $attachments[] = ['name' => $name, 'size' => (int) $a->size];

                // Auto-import des clés PGP jointes au mail (queue RabbitMQ).
                $mime = strtolower((string) ($a->content_type ?? ''));
                $isPgpKey = str_contains($mime, 'pgp-keys')
                    || preg_match('/\.asc$/i', $name)
                    || preg_match('/\.gpg$/i', $name);
                if ($isPgpKey && ! $pgpKeyImported) {
                    try {
                        $content = (string) $a->getContent();
                        if (str_contains($content, 'BEGIN PGP PUBLIC KEY BLOCK') && $from['mail']) {
                            app(MailQueue::class)->publish([
                                'owner'        => $email,
                                'sender_email' => $from['mail'],
                                'armored'      => $content,
                            ], MailQueue::QUEUE_KEYIMPORT);
                            $pgpKeyImported = true;
                        }
                    } catch (\Throwable $e) {}
                }
            }
        } catch (\Throwable $e) {
        }

        $messageId = '';
        try {
            $messageId = trim((string) $m->getMessageId());
        } catch (\Throwable $e) {
        }

        // Parse Authentication-Results pour exposer DKIM / SPF / DMARC.
        $auth = ['dkim' => null, 'spf' => null, 'dmarc' => null, 'pgp_signed' => false];
        try {
            $ar = (string) $m->getHeader('authentication_results');
            if ($ar !== '') {
                foreach (['dkim', 'spf', 'dmarc'] as $k) {
                    if (preg_match('/\b' . $k . '\s*=\s*([a-z]+)/i', $ar, $mm)) {
                        $auth[$k] = strtolower($mm[1]); // pass / fail / softfail / neutral / none
                    }
                }
            }
            // Détection signature PGP inline (---BEGIN PGP SIGNED MESSAGE---)
            $body = ($m->getTextBody() ?: '') . ($m->getHTMLBody() ?: '');
            if (str_contains($body, '-----BEGIN PGP SIGNED MESSAGE-----')
             || str_contains($body, '-----BEGIN PGP SIGNATURE-----')) {
                $auth['pgp_signed'] = true;
            }
        } catch (\Throwable $e) {
        }

        // Demande d'accusé de réception (RFC 3798 + variantes)
        $receiptTo = '';
        try {
            foreach (['disposition_notification_to', 'return_receipt_to', 'x_confirm_reading_to'] as $h) {
                $v = trim((string) $m->getHeader($h));
                if ($v !== '') {
                    if (preg_match('/<([^>]+)>/', $v, $mm)) $v = $mm[1];
                    if (filter_var($v, FILTER_VALIDATE_EMAIL)) { $receiptTo = $v; break; }
                }
            }
        } catch (\Throwable $e) {
        }

        $data = [
            'uid'         => (int) $m->getUid(),
            'message_id'  => $messageId,
            'subject'     => $this->mimeDecode((string) $m->getSubject()) ?: '(sans objet)',
            'from'        => $from['name'],
            'from_mail'   => $from['mail'],
            'from_hash'   => $from['mail'] ? md5(strtolower(trim($from['mail']))) : '',
            'to'          => implode(', ', $to),
            'date'        => $this->dateOf($m),
            'html'        => self::rewriteMailHtml($m->getHTMLBody() ?: null),
            'receipt_to'  => $receiptTo,
            'auth'        => $auth,
            'text'        => $m->getTextBody() ?: null,
            'attachments' => $attachments,
        ];
        try {
            $m->setFlag('Seen');
        } catch (\Throwable $e) {
        }
        $client->disconnect();
        return $data;
    }

    /** Source RFC822 brute du message (équivalent Gmail « Afficher l'original »). */
    public function raw(string $email, string $folder, int $uid): ?string
    {
        $client = $this->client($email);
        $box = $client->getFolder($folder);
        $m = $box->query()->getMessageByUid($uid);
        if (! $m) { $client->disconnect(); return null; }
        $raw = '';
        try {
            // Webklex expose le raw via getStructure + body parts.
            // Parenthèses : sans elles `(string) null ?? ''` retourne "" (le cast
            // a la priorité sur ??), donc le fallback ne se déclenche jamais.
            $hdrs = (string) ($m->getHeader()?->raw ?? '');
            $body = (string) $m->getRawBody();
            $raw = trim($hdrs) . "\r\n\r\n" . $body;
        } catch (\Throwable $e) {}
        $client->disconnect();
        return $raw ?: null;
    }

    /** Récupère le contenu binaire d'une pièce jointe pour téléchargement / preview. */
    public function attachment(string $email, string $folder, int $uid, string $name): ?array
    {
        $client = $this->client($email);
        $box = $client->getFolder($folder);
        $m = $box->query()->getMessageByUid($uid);
        if (! $m) { $client->disconnect(); return null; }
        foreach ($m->getAttachments() as $a) {
            if ((string) $a->name === $name) {
                $out = ['name' => $name, 'mime' => (string) ($a->content_type ?: 'application/octet-stream'),
                    'content' => (string) $a->getContent()];
                $client->disconnect();
                return $out;
            }
        }
        $client->disconnect();
        return null;
    }

    /**
     * Récupère les UID des autres messages du même fil de discussion
     * (sujet normalisé Re:/Fwd: ignorés) dans le dossier courant.
     */
    public function threadOf(string $email, string $folder, string $subject): array
    {
        $norm = function ($s) {
            return mb_strtolower(trim(preg_replace('/^\s*(re|fwd?|tr)\s*:\s*/i', '', (string) $s)));
        };
        $needle = $norm($subject);
        if ($needle === '') return [];

        $client = $this->client($email);
        $box = $client->getFolder($folder);
        $out = [];
        try {
            $msgs = $box->query()->all()->limit(60)->setFetchOrder('desc')->setFetchBody(false)->get();
            foreach ($msgs as $m) {
                if ($norm($m->getSubject()) !== $needle) continue;
                $from = $this->addr($m->getFrom());
                $out[] = [
                    'uid'       => (int) $m->getUid(),
                    'from'      => $from['name'] ?: $from['mail'],
                    'from_mail' => $from['mail'],
                    'from_hash' => $from['mail'] ? md5(strtolower(trim($from['mail']))) : '',
                    'date'      => $this->dateOf($m),
                    'seen'      => $this->flagSeen($m),
                ];
            }
        } catch (\Throwable $e) {}
        $client->disconnect();
        // tri chronologique ASC dans le thread
        usort($out, fn ($a, $b) => strcmp((string) ($a['date'] ?? ''), (string) ($b['date'] ?? '')));
        return $out;
    }

    /**
     * Espace occupé par la boîte (somme des tailles de tous les messages)
     * et quota total. Mis en cache 2 min pour éviter un scan à chaque page.
     */
    public function quota(string $email): array
    {
        // Le quota TOTAL est lu en direct depuis la DB pour refléter
        // immédiatement les changements admin (sinon Dovecot cache via
        // maildirsize jusqu'au prochain LOGIN IMAP).
        // Fallback config si la DB n'a pas la table (déploiement où mail_accounts
        // vit dans un autre service / DB séparée).
        $row = null;
        try {
            $row = \Illuminate\Support\Facades\DB::table('mail_accounts')
                ->where('email', strtolower(trim($email)))
                ->value('quota_bytes');
        } catch (\Throwable $e) {
            // Pas de table mail_accounts dans la DB Laravel : on retombe sur env.
        }
        $limit = (int) ($row ?? config('mail.quota_bytes'));

        $used = \Illuminate\Support\Facades\Cache::remember(
            'mail_quota_' . md5($email), 120,
            function () use ($email) {
                $total = 0;
                try {
                    $client = $this->client($email);
                    foreach ($client->getFolders(false) as $folder) {
                        try {
                            $box = $client->getFolder($folder->path);
                            $messages = $box->query()->all()->setFetchBody(false)->get();
                            foreach ($messages as $m) {
                                $total += (int) $m->getSize();
                            }
                        } catch (\Throwable $e) {
                        }
                    }
                    $client->disconnect();
                } catch (\Throwable $e) {
                }
                return $total;
            }
        );

        return [
            'used'    => $used,
            'limit'   => $limit,
            'percent' => $limit > 0 ? min(100, round($used / $limit * 100, 1)) : 0,
        ];
    }

    /**
     * Enregistre un brouillon dans le dossier IMAP « Brouillons ».
     * Si $prevUid est fourni, la version précédente est supprimée juste après.
     * Retourne le nouvel UID du brouillon, ou null si introuvable.
     */
    public function saveDraft(string $email, string $fromName, array $payload, ?int $prevUid = null): ?int
    {
        $client = $this->client($email);
        $folder = $this->specialFolderPath($client, 1, ['Drafts']);
        if (! $folder) {
            $client->disconnect();
            throw new \RuntimeException('Dossier Brouillons indisponible.');
        }
        $box = $client->getFolder($folder);

        // Construire le raw RFC822 via Symfony Mime
        $sender = $fromName !== '' ? new \Symfony\Component\Mime\Address($email, $fromName)
                                   : new \Symfony\Component\Mime\Address($email);
        $msg = (new \Symfony\Component\Mime\Email())
            ->from($sender)
            ->subject(($payload['subject'] ?? '') !== '' ? $payload['subject'] : '(sans objet)')
            ->html(($payload['html'] ?? '') !== '' ? $payload['html'] : '&nbsp;');

        foreach ((array) ($payload['to'] ?? []) as $a) {
            $a = trim((string) $a);
            if ($a !== '' && filter_var($a, FILTER_VALIDATE_EMAIL)) {
                $msg->addTo($a);
            }
        }
        foreach ((array) ($payload['cc'] ?? []) as $a) {
            $a = trim((string) $a);
            if ($a !== '' && filter_var($a, FILTER_VALIDATE_EMAIL)) {
                $msg->addCc($a);
            }
        }
        if (! empty($payload['in_reply_to'])) {
            $msg->getHeaders()->addTextHeader('In-Reply-To', $payload['in_reply_to']);
            $msg->getHeaders()->addTextHeader('References', $payload['in_reply_to']);
        }
        $msg->getHeaders()->addTextHeader('X-Noliae-Draft', '1');

        $raw = $msg->toString();
        $box->appendMessage($raw, ['\\Seen', '\\Draft']);

        // Retrouver l'UID du brouillon fraîchement ajouté (plus récent par date).
        $newUid = null;
        try {
            $last = $box->query()->all()->setFetchBody(false)->setFetchOrder('desc')->limit(1)->get()->first();
            if ($last) {
                $newUid = (int) $last->getUid();
            }
        } catch (\Throwable $e) {
        }

        // Supprimer l'ancienne version (après écriture pour ne pas perdre en cas d'échec).
        if ($prevUid && $prevUid !== $newUid) {
            try {
                $old = $box->query()->getMessageByUid($prevUid);
                if ($old) {
                    $old->delete();
                }
            } catch (\Throwable $e) {
            }
        }

        $client->disconnect();
        return $newUid;
    }

    /** Copie best-effort d'un message envoyé dans le dossier « Envoyés ». */
    public function appendSent(string $email, string $raw): void
    {
        try {
            $client = $this->client($email);
            $sent = null;
            foreach (['Sent', 'Sent Messages', 'Envoyés', 'INBOX.Sent'] as $name) {
                try {
                    $f = $client->getFolder($name);
                    if ($f) {
                        $sent = $f;
                        break;
                    }
                } catch (\Throwable $e) {
                }
            }
            if (! $sent) {
                $client->createFolder('Sent');
                $sent = $client->getFolder('Sent');
            }
            $sent->appendMessage($raw, ['\\Seen']);
            $client->disconnect();
        } catch (\Throwable $e) {
        }
    }
}
