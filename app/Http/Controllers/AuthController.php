<?php

namespace App\Http\Controllers;

use App\Services\AppSettings;
use App\Services\MailboxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

/**
 * Noliae Mail OSS — authentification locale.
 *
 * Comptes stockés dans `mail_accounts` (même table que la version Noliae).
 * Mot de passe IMAP/SMTP haché en BCRYPT (compatible Dovecot/Postfix lookup).
 *
 *   POST /login    → email + password, ouvre la session webmail
 *   POST /register → crée un compte (activable ou désactivable via .env)
 */
class AuthController extends Controller
{
    public function __construct(private MailboxService $mail) {}

    /** GET / — page d'accueil : si déjà connecté redirige /webmail, sinon Login. */
    public function landing(Request $request)
    {
        if ($request->session()->get('mail_user')) {
            return redirect('/webmail');
        }
        return Inertia::render('Login', [
            'allowRegister' => AppSettings::bool('allow_registration', false),
            'domain'        => config('mail.primary_domain'),
        ]);
    }

    public function showLogin(Request $request) { return $this->landing($request); }

    /** GET /admin/login — page de connexion dédiée Admin Center (style OWA Admin). */
    public function showAdminLogin(Request $request)
    {
        if ($request->session()->get('mail_user')) {
            return redirect('/admin');
        }
        return Inertia::render('AdminLogin', [
            'domain'      => config('mail.primary_domain'),
            'org_name'    => \Illuminate\Support\Facades\DB::table('organizations')->value('name'),
        ]);
    }

    public function showRegister(Request $request)
    {
        if (! AppSettings::bool('allow_registration', false)) abort(403, 'Inscriptions désactivées par l\'administrateur.');
        return Inertia::render('Login', [
            'allowRegister' => true,
            'registerMode'  => true,
            'domain'        => config('mail.primary_domain'),
        ]);
    }

    /** POST /login — protégé par throttle + constant-time + dummy hash + challenge 2FA si activé. */
    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email:rfc|max:255',
            'password' => 'required|string|max:200',
        ]);
        $email = strtolower(trim($data['email']));
        $row = DB::table('mail_accounts')->where('email', $email)->where('active', true)->first();
        $stored = $row->password ?? '{BLF-CRYPT}$2y$12$invalidhashplaceholderXXXXXXXXXXXXXXXXXXXXXXXXXXXX';
        $ok = $this->verifyPassword($data['password'], $stored);
        if (! $row || ! $ok) {
            return back()->withErrors(['email' => 'Identifiants invalides.']);
        }
        // Si 2FA activé : on garde le user en "pending_2fa" et on demande le code.
        if (! empty($row->totp_enabled)) {
            $request->session()->put('pending_2fa_email', $email);
            return redirect('/login/2fa');
        }
        $request->session()->regenerate();
        $request->session()->put('mail_user', $email);
        $request->session()->put('mail_name', $row->display_name ?? '');
        return redirect('/webmail');
    }

    /** GET /login/2fa — formulaire challenge TOTP. */
    public function show2fa(Request $request)
    {
        if (! $request->session()->get('pending_2fa_email')) return redirect('/login');
        return \Inertia\Inertia::render('Login2fa', []);
    }

    /** POST /login/2fa — vérifie le code TOTP. */
    public function verify2fa(Request $request)
    {
        $email = (string) $request->session()->get('pending_2fa_email');
        if (! $email) return redirect('/login');
        $data = $request->validate(['code' => 'required|string|max:25']);
        $row = DB::table('mail_accounts')->where('email', $email)->first();
        if (! $row || ! $row->totp_secret) return redirect('/login');

        $code = trim($data['code']);
        $ok = false;

        // Try TOTP code
        try {
            $secret = \Illuminate\Support\Facades\Crypt::decryptString($row->totp_secret);
            if (\App\Services\Totp::verify($secret, $code)) $ok = true;
        } catch (\Throwable $e) {}

        // Try recovery code (one-shot)
        if (! $ok && $row->totp_recovery) {
            try {
                $codes = json_decode(\Illuminate\Support\Facades\Crypt::decryptString($row->totp_recovery), true) ?: [];
                $idx = array_search(strtolower($code), array_map('strtolower', $codes), true);
                if ($idx !== false) {
                    $ok = true;
                    unset($codes[$idx]);
                    DB::table('mail_accounts')->where('id', $row->id)->update([
                        'totp_recovery' => \Illuminate\Support\Facades\Crypt::encryptString(json_encode(array_values($codes))),
                    ]);
                }
            } catch (\Throwable $e) {}
        }

        if (! $ok) {
            return back()->withErrors(['code' => 'Code invalide ou expiré.']);
        }
        $request->session()->forget('pending_2fa_email');
        $request->session()->regenerate();
        $request->session()->put('mail_user', $email);
        $request->session()->put('mail_name', $row->display_name ?? '');
        return redirect('/webmail');
    }

    /** POST /register — crée un compte sur le domaine principal. */
    public function register(Request $request)
    {
        if (! AppSettings::bool('allow_registration', false)) abort(403, 'Inscriptions désactivées par l\'administrateur.');
        $domain = config('mail.primary_domain');
        $data = $request->validate([
            'local'    => 'required|string|max:64|regex:/^[a-z0-9][a-z0-9._-]{1,63}$/i',
            'password' => 'required|string|min:10|max:200',
            'display_name' => 'nullable|string|max:120',
        ]);
        $email = strtolower($data['local']) . '@' . $domain;
        if (DB::table('mail_accounts')->where('email', $email)->exists()) {
            return back()->withErrors(['local' => 'Cette adresse est déjà prise.']);
        }
        $domainRow = DB::table('mail_domains')->where('name', $domain)->first();
        if (! $domainRow) {
            $domainId = DB::table('mail_domains')->insertGetId([
                'name' => $domain, 'active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        } else {
            $domainId = $domainRow->id;
        }
        // BCRYPT format compatible Dovecot password lookup ({BLF-CRYPT}…)
        $hash = '{BLF-CRYPT}' . password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        DB::table('mail_accounts')->insert([
            'domain_id'    => $domainId,
            'email'        => $email,
            'password'     => $hash,
            'display_name' => $data['display_name'] ?? null,
            'quota_bytes'  => AppSettings::int('default_quota_mb', 5120) * 1024 * 1024,
            'maildir'      => $domain . '/' . strtolower($data['local']) . '/',
            'active'       => true,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        $request->session()->regenerate();
        $request->session()->put('mail_user', $email);
        $request->session()->put('mail_name', $data['display_name'] ?? '');
        return redirect('/webmail');
    }

    public function logout(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }

    /**
     * GET /logout — page intermédiaire qui POST automatiquement.
     * On garde l'URL bookmarkable / partageable sans réintroduire la vulnérabilité
     * CSRF logout (un <img src=/logout> distant n'exécutera pas le JS).
     */
    public function logoutConfirm(Request $request)
    {
        if (! $request->session()->get('mail_user')) {
            return redirect('/');
        }
        $token = csrf_token();
        return response('<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Déconnexion…</title>'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<style>body{font-family:system-ui,sans-serif;background:#15151a;color:#FAFAF7;'
            . 'display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}'
            . '.box{text-align:center;padding:2rem}.btn{display:inline-block;padding:.75rem 1.5rem;'
            . 'border-radius:999px;background:#FF4D2E;color:#fff;font-weight:700;border:0;cursor:pointer;font-size:14px}'
            . '.btn:hover{background:#df3c1f}</style></head><body><div class="box">'
            . '<p style="margin-bottom:1rem">Confirmer la déconnexion ?</p>'
            . '<form method="POST" action="/logout">'
            . '<input type="hidden" name="_token" value="' . htmlspecialchars($token, ENT_QUOTES) . '">'
            . '<button class="btn" type="submit" autofocus>Se déconnecter</button>'
            . '</form><script>document.forms[0].submit()</script></div></body></html>');
    }

    /** Accepte {BLF-CRYPT}$2y$… ou {SHA512-CRYPT}… ou {PLAIN}… */
    private function verifyPassword(string $plain, string $stored): bool
    {
        if (str_starts_with($stored, '{BLF-CRYPT}')) {
            return password_verify($plain, substr($stored, strlen('{BLF-CRYPT}')));
        }
        // Whitelist stricte : on REFUSE explicitement {PLAIN} et autres schémas
        // pour éviter qu'un mot de passe stocké en clair en DB soit acceptable
        // et pour éviter la branche password_verify aveugle (timing leak +
        // possible match accidentel sur un hash mal-formé).
        return false;
    }
}
