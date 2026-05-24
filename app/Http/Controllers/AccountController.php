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
        ]);
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
