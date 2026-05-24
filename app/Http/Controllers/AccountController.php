<?php

namespace App\Http\Controllers;

use App\Services\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Noliae Mail OSS — espace user /account.
 *  - Voir / changer mot de passe
 *  - Activer / désactiver TOTP 2FA
 *  - Générer / révoquer des tokens SMTP (mots de passe app)
 */
class AccountController extends Controller
{
    /** Récupère le compte connecté ou aborte 401. */
    private function account(Request $request): object
    {
        $email = strtolower((string) $request->session()->get('mail_user', ''));
        abort_unless($email, 401);
        $row = DB::table('mail_accounts')->where('email', $email)->first();
        abort_unless($row, 404);
        return $row;
    }

    public function index(Request $request)
    {
        $acc = $this->account($request);
        $tokens = DB::table('smtp_tokens')->where('mail_account_id', $acc->id)
            ->orderByDesc('created_at')
            ->get(['id', 'label', 'last_used_at', 'created_at'])->toArray();
        return Inertia::render('Account', [
            'email'        => $acc->email,
            'display_name' => $acc->display_name,
            'totp_enabled' => (bool) $acc->totp_enabled,
            'tokens'       => $tokens,
            'mail_domain'  => config('mail.primary_domain'),
            'avatar_hash'  => md5(strtolower($acc->email)),
            'avatar_url'   => $acc->avatar_path
                ? '/webmail/avatar/' . md5(strtolower($acc->email)) . '?v=' . substr(md5((string) $acc->avatar_path), 0, 8)
                : null,
            // Profil étendu (migration 110)
            'profile' => [
                'first_name'  => $acc->first_name  ?? null,
                'last_name'   => $acc->last_name   ?? null,
                'initials'    => $acc->initials    ?? null,
                'phone'       => $acc->phone       ?? null,
                'mobile'      => $acc->mobile      ?? null,
                'job_title'   => $acc->job_title   ?? null,
                'company'     => $acc->company     ?? null,
                'street'      => $acc->street      ?? null,
                'city'        => $acc->city        ?? null,
                'state'       => $acc->state       ?? null,
                'postal_code' => $acc->postal_code ?? null,
                'country'     => $acc->country     ?? null,
                'timezone'    => $acc->timezone    ?? 'UTC',
                'language'    => $acc->language    ?? 'fr',
            ],
            'force_2fa' => $request->boolean('force_2fa'),
        ]);
    }

    /** POST /account/profile — met à jour les champs profil étendus + display_name. */
    public function updateProfile(Request $request)
    {
        $acc = $this->account($request);
        $data = $request->validate([
            'display_name' => 'nullable|string|max:120',
            'first_name'   => 'nullable|string|max:80',
            'last_name'    => 'nullable|string|max:80',
            'initials'     => 'nullable|string|max:8',
            'phone'        => 'nullable|string|max:32',
            'mobile'       => 'nullable|string|max:32',
            'job_title'    => 'nullable|string|max:120',
            'company'      => 'nullable|string|max:120',
            'street'       => 'nullable|string|max:255',
            'city'         => 'nullable|string|max:120',
            'state'        => 'nullable|string|max:120',
            'postal_code'  => 'nullable|string|max:20',
            'country'      => 'nullable|string|max:80',
            'timezone'     => 'nullable|string|max:64',
            'language'     => 'nullable|string|max:8',
        ]);
        DB::table('mail_accounts')->where('id', $acc->id)->update(array_merge($data, [
            'updated_at' => now(),
        ]));
        return back()->with('success', 'Profil mis à jour.');
    }

    /* ──────── Avatar ──────── */

    public function uploadAvatar(Request $request)
    {
        $acc = $this->account($request);
        $request->validate([
            'avatar' => 'required|file|image|mimes:jpeg,png,webp,gif|max:2048',
        ]);
        $file = $request->file('avatar');
        // On dérive l'extension du MIME RÉEL plutôt que de l'extension client
        // (qu'on ne contrôle pas : un attaquant pourrait uploader foo.php
        // si le serveur web était mal configuré). Le mimes() de validate()
        // a déjà whitelisté le type, mais on durcit le nom de fichier final.
        $mime = (string) ($file->getMimeType() ?: '');
        $ext  = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
            default      => 'png',
        };
        $dir  = storage_path('app/private/avatars');
        if (! is_dir($dir)) @mkdir($dir, 0750, true);
        $hash = md5(strtolower($acc->email));
        // On garde un seul fichier par user (efface l'ancien si extension différente).
        foreach (glob($dir . '/' . $hash . '.*') ?: [] as $old) { @unlink($old); }
        $rel = 'avatars/' . $hash . '.' . $ext;
        $file->move($dir, $hash . '.' . $ext);
        DB::table('mail_accounts')->where('id', $acc->id)->update([
            'avatar_path' => $rel,
            'updated_at'  => now(),
        ]);
        return back()->with('success', 'Avatar mis à jour.');
    }

    public function deleteAvatar(Request $request)
    {
        $acc = $this->account($request);
        if ($acc->avatar_path) {
            @unlink(storage_path('app/private/' . $acc->avatar_path));
        }
        DB::table('mail_accounts')->where('id', $acc->id)->update([
            'avatar_path' => null,
            'updated_at'  => now(),
        ]);
        return back()->with('success', 'Avatar supprimé.');
    }

    /**
     * GET /webmail/avatar/{hash}
     *
     * Public, cacheable. Renvoie l'image stockée si le hash correspond à un
     * compte qui a uploadé un avatar — sinon génère un SVG d'initiales coloré
     * déterministe (basé sur le hash + ?n=nom).
     */
    public function serveAvatar(Request $request, string $hash)
    {
        try {
            $hash = strtolower(preg_replace('/[^a-f0-9]/', '', $hash));
            if (strlen($hash) === 32) {
                $row = DB::table('mail_accounts')
                    ->whereRaw('md5(lower(email)) = ?', [$hash])
                    ->whereNotNull('avatar_path')
                    ->select('avatar_path')
                    ->first();
                if ($row) {
                    $path = storage_path('app/private/' . $row->avatar_path);
                    if (is_readable($path)) {
                        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
                            'jpg', 'jpeg' => 'image/jpeg',
                            'png'         => 'image/png',
                            'webp'        => 'image/webp',
                            'gif'         => 'image/gif',
                            default       => 'application/octet-stream',
                        };
                        return response()->file($path, [
                            'Content-Type'  => $mime,
                            'Cache-Control' => 'public, max-age=86400',
                        ]);
                    }
                }
            }
            // Fallback : SVG initiales (Gravatar-like).
            $name = (string) $request->query('n', '');
            $svg  = $this->initialsSvg($name !== '' ? $name : '?', $hash ?: '0');
        } catch (\Throwable $e) {
            \Log::warning('serveAvatar fallback: ' . $e->getMessage());
            $svg = $this->initialsSvg('?', '0');
        }
        return response($svg, 200, [
            'Content-Type'  => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function initialsSvg(string $name, string $seed): string
    {
        $parts = preg_split('/[\s.@_+-]+/u', trim($name)) ?: [];
        $parts = array_values(array_filter($parts, fn ($p) => $p !== ''));
        if (count($parts) >= 2) {
            $ini = mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
        } else {
            $ini = mb_strtoupper(mb_substr($name, 0, 2));
        }
        // Couleur déterministe à partir du seed
        $n = hexdec(substr($seed ?: md5($name), 0, 6));
        $h = $n % 360;
        $bg1 = "hsl($h, 70%, 55%)";
        $bg2 = 'hsl(' . (($h + 40) % 360) . ', 70%, 45%)';
        $ini = htmlspecialchars($ini, ENT_QUOTES | ENT_XML1, 'UTF-8');
        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="64" height="64">
  <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
    <stop offset="0" stop-color="$bg1"/><stop offset="1" stop-color="$bg2"/>
  </linearGradient></defs>
  <rect width="64" height="64" rx="32" fill="url(#g)"/>
  <text x="50%" y="54%" text-anchor="middle" dominant-baseline="middle"
        font-family="-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif"
        font-size="26" font-weight="700" fill="#fff">$ini</text>
</svg>
SVG;
    }

    /* ──────── Mot de passe ──────── */

    public function changePassword(Request $request)
    {
        $acc = $this->account($request);
        $data = $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:10|max:200|different:current_password',
        ]);
        // Vérifie le password actuel (schemes Dovecot supportés)
        if (! $this->verifyDovecotPassword($data['current_password'], (string) $acc->password)) {
            return back()->withErrors(['current_password' => 'Mot de passe actuel incorrect.']);
        }
        $newHash = '{BLF-CRYPT}' . password_hash($data['new_password'], PASSWORD_BCRYPT, ['cost' => 12]);
        DB::table('mail_accounts')->where('id', $acc->id)->update([
            'password'   => $newHash,
            'updated_at' => now(),
        ]);
        return back()->with('success', 'Mot de passe changé. Re-connexion requise sur tes clients mail.');
    }

    /* ──────── 2FA TOTP ──────── */

    public function start2fa(Request $request)
    {
        $acc = $this->account($request);
        if ($acc->totp_enabled) {
            return back()->withErrors(['totp' => 'Le 2FA est déjà activé.']);
        }
        // Génère un nouveau secret, le stocke chiffré (état pending), retourne uri+code
        $secret = Totp::generateSecret();
        DB::table('mail_accounts')->where('id', $acc->id)->update([
            'totp_secret' => Crypt::encryptString($secret),
            'updated_at'  => now(),
        ]);
        return back()->with('totp_setup', [
            'secret' => $secret,
            'uri'    => Totp::uri($secret, $acc->email),
        ]);
    }

    public function confirm2fa(Request $request)
    {
        $acc = $this->account($request);
        $data = $request->validate(['code' => 'required|string|size:6']);
        if (! $acc->totp_secret) {
            return back()->withErrors(['code' => 'Aucune configuration 2FA en cours.']);
        }
        $secret = Crypt::decryptString($acc->totp_secret);
        if (! Totp::verify($secret, $data['code'])) {
            return back()->withErrors(['code' => 'Code invalide. Vérifie ton application d\'authentification.']);
        }
        // Génère 8 recovery codes one-shot
        $recovery = [];
        for ($i = 0; $i < 8; $i++) {
            $recovery[] = strtolower(bin2hex(random_bytes(4)) . '-' . bin2hex(random_bytes(4)));
        }
        DB::table('mail_accounts')->where('id', $acc->id)->update([
            'totp_enabled'    => true,
            'totp_enabled_at' => now(),
            'totp_recovery'   => Crypt::encryptString(json_encode($recovery)),
            'updated_at'      => now(),
        ]);
        return back()->with('totp_activated', [
            'recovery_codes' => $recovery,
            'message'        => '2FA activé. Note les codes de récupération ci-dessous — ils ne seront PLUS jamais affichés.',
        ]);
    }

    public function disable2fa(Request $request)
    {
        $acc = $this->account($request);
        $data = $request->validate(['password' => 'required|string']);
        if (! $this->verifyDovecotPassword($data['password'], (string) $acc->password)) {
            return back()->withErrors(['password' => 'Mot de passe incorrect.']);
        }
        DB::table('mail_accounts')->where('id', $acc->id)->update([
            'totp_secret'     => null,
            'totp_enabled'    => false,
            'totp_recovery'   => null,
            'totp_enabled_at' => null,
            'updated_at'      => now(),
        ]);
        return back()->with('success', '2FA désactivé.');
    }

    /* ──────── SMTP tokens ──────── */

    public function createToken(Request $request)
    {
        $acc = $this->account($request);
        $data = $request->validate(['label' => 'required|string|max:100']);
        // 24 chars lisibles (sans 0/O/1/l ambigus)
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $plain = '';
        for ($i = 0; $i < 24; $i++) {
            $plain .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $hash = '{BLF-CRYPT}' . password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);
        DB::table('smtp_tokens')->insert([
            'mail_account_id' => $acc->id,
            'label'           => trim($data['label']),
            'password_hash'   => $hash,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
        return back()->with('new_token', [
            'label'    => $data['label'],
            'password' => $plain,
            'server'   => config('mail.primary_domain'),
            'port'     => 587,
            'username' => $acc->email,
            'warning'  => 'Note ce mot de passe MAINTENANT — il ne sera plus jamais affiché.',
        ]);
    }

    public function deleteToken(Request $request, int $id)
    {
        $acc = $this->account($request);
        DB::table('smtp_tokens')->where('id', $id)
            ->where('mail_account_id', $acc->id)->delete();
        return back()->with('success', 'Token SMTP révoqué.');
    }

    /**
     * Vérifie un mot de passe selon le scheme Dovecot stocké en DB
     * ({BLF-CRYPT}, {SHA512-CRYPT}, {CRYPT}, ou bcrypt brut).
     */
    private function verifyDovecotPassword(string $plain, string $stored): bool
    {
        $stored = trim($stored);
        if ($stored === '') return false;
        if (preg_match('/^\{([A-Z0-9-]+)\}(.+)$/', $stored, $m)) {
            $scheme = strtoupper($m[1]);
            $hash   = $m[2];
            if (in_array($scheme, ['BLF-CRYPT', 'SHA512-CRYPT', 'SHA256-CRYPT', 'MD5-CRYPT', 'CRYPT'], true)) {
                // crypt() détecte le format via le préfixe ($2y$ / $6$ / $5$ / $1$)
                return hash_equals(crypt($plain, $hash), $hash);
            }
            if ($scheme === 'PLAIN' || $scheme === 'CLEARTEXT') {
                return hash_equals($hash, $plain);
            }
            // Schemes non gérés ici : refuse plutôt que d'auto-passer.
            return false;
        }
        // Pas de préfixe : on suppose bcrypt
        return password_verify($plain, $stored);
    }
}
