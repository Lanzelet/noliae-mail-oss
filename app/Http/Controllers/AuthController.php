<?php

namespace App\Http\Controllers;

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
            'allowRegister' => (bool) env('ALLOW_REGISTRATION', true),
            'domain'        => config('mail.primary_domain'),
        ]);
    }

    public function showLogin(Request $request) { return $this->landing($request); }

    public function showRegister(Request $request)
    {
        if (! env('ALLOW_REGISTRATION', true)) abort(403, 'Registration disabled');
        return Inertia::render('Login', [
            'allowRegister' => true,
            'registerMode'  => true,
            'domain'        => config('mail.primary_domain'),
        ]);
    }

    /** POST /login */
    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email|max:255',
            'password' => 'required|string|max:200',
        ]);
        $email = strtolower(trim($data['email']));
        $row = DB::table('mail_accounts')->where('email', $email)->where('active', true)->first();
        if (! $row || ! $this->verifyPassword($data['password'], $row->password)) {
            return back()->withErrors(['email' => 'Identifiants invalides.']);
        }
        $request->session()->regenerate();
        $request->session()->put('mail_user', $email);
        $request->session()->put('mail_name', $row->display_name ?? '');
        return redirect('/webmail');
    }

    /** POST /register — crée un compte sur le domaine principal. */
    public function register(Request $request)
    {
        if (! env('ALLOW_REGISTRATION', true)) abort(403, 'Registration disabled');
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
            'quota_bytes'  => config('mail.quota_bytes'),
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

    /** Accepte {BLF-CRYPT}$2y$… ou {SHA512-CRYPT}… ou {PLAIN}… */
    private function verifyPassword(string $plain, string $stored): bool
    {
        if (str_starts_with($stored, '{BLF-CRYPT}')) {
            return password_verify($plain, substr($stored, strlen('{BLF-CRYPT}')));
        }
        if (str_starts_with($stored, '{PLAIN}')) {
            return hash_equals(substr($stored, 7), $plain);
        }
        // Fallback : si pas de préfixe Dovecot, essai password_verify direct
        return password_verify($plain, $stored);
    }
}
