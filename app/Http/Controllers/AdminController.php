<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

/**
 * Noliae Mail OSS — panneau d'administration.
 *
 * Accès : tout user dont l'email == env('ADMIN_EMAIL').
 * URL  : /admin
 *
 * Fonctionnalités MVP :
 *   - Tableau de bord (stats : nb domaines, nb comptes, espace)
 *   - Gestion des domaines (créer, voir, supprimer + records DNS requis)
 *   - Gestion des comptes (créer, suspendre, supprimer, reset password)
 *   - Paramètres (backup schedule, ALLOW_REGISTRATION)
 */
class AdminController extends Controller
{
    /** Garde-fou admin. */
    private function authorize_admin(Request $request): void
    {
        $user = strtolower((string) $request->session()->get('mail_user', ''));
        $admin = strtolower((string) env('ADMIN_EMAIL', ''));
        abort_unless($user && $admin && $user === $admin, 403, 'Accès admin réservé.');
    }

    public function dashboard(Request $request)
    {
        $this->authorize_admin($request);
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'domains'   => DB::table('mail_domains')->count(),
                'accounts'  => DB::table('mail_accounts')->count(),
                'active'    => DB::table('mail_accounts')->where('active', true)->count(),
                'suspended' => DB::table('mail_accounts')->where('active', false)->count(),
            ],
            'recent' => DB::table('mail_accounts')
                ->orderByDesc('created_at')->limit(10)
                ->get(['id','email','display_name','active','created_at'])->toArray(),
            'admin_email' => env('ADMIN_EMAIL'),
            'app_domain'  => config('mail.primary_domain'),
        ]);
    }

    public function domains(Request $request)
    {
        $this->authorize_admin($request);
        $domains = DB::table('mail_domains')
            ->leftJoin('mail_accounts', 'mail_accounts.domain_id', '=', 'mail_domains.id')
            ->selectRaw('mail_domains.*, COUNT(mail_accounts.id) as accounts_count')
            ->groupBy('mail_domains.id','mail_domains.name','mail_domains.active','mail_domains.created_at','mail_domains.updated_at')
            ->orderBy('mail_domains.name')->get()->toArray();
        return Inertia::render('Admin/Domains', ['domains' => $domains]);
    }

    public function createDomain(Request $request)
    {
        $this->authorize_admin($request);
        $data = $request->validate([
            'name' => 'required|string|max:253|regex:/^[a-z0-9][a-z0-9.-]{1,253}\.[a-z]{2,}$/i',
        ]);
        $name = strtolower(trim($data['name']));
        if (DB::table('mail_domains')->where('name', $name)->exists()) {
            return back()->withErrors(['name' => 'Ce domaine existe déjà.']);
        }
        DB::table('mail_domains')->insert([
            'name'       => $name,
            'active'     => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return redirect('/admin/domains')->with('success', "Domaine $name ajouté.");
    }

    public function deleteDomain(Request $request, int $id)
    {
        $this->authorize_admin($request);
        $hasAccounts = DB::table('mail_accounts')->where('domain_id', $id)->exists();
        if ($hasAccounts) {
            return back()->withErrors(['name' => 'Impossible : il existe des comptes sur ce domaine.']);
        }
        DB::table('mail_domains')->where('id', $id)->delete();
        return back()->with('success', 'Domaine supprimé.');
    }

    public function accounts(Request $request)
    {
        $this->authorize_admin($request);
        $accounts = DB::table('mail_accounts')
            ->join('mail_domains','mail_domains.id','=','mail_accounts.domain_id')
            ->select('mail_accounts.id','mail_accounts.email','mail_accounts.display_name',
                     'mail_accounts.active','mail_accounts.quota_bytes','mail_accounts.created_at',
                     'mail_domains.name as domain_name')
            ->orderBy('mail_accounts.email')->get()->toArray();
        $domains = DB::table('mail_domains')->where('active', true)
            ->orderBy('name')->get(['id','name'])->toArray();
        return Inertia::render('Admin/Accounts', [
            'accounts' => $accounts,
            'domains'  => $domains,
        ]);
    }

    public function createAccount(Request $request)
    {
        $this->authorize_admin($request);
        $data = $request->validate([
            'local'        => 'required|string|max:64|regex:/^[a-z0-9][a-z0-9._-]{1,63}$/i',
            'domain_id'    => 'required|integer|exists:mail_domains,id',
            'password'     => 'required|string|min:10|max:200',
            'display_name' => 'nullable|string|max:120',
            'quota_mb'     => 'nullable|integer|min:10|max:102400',
        ]);
        $dom = DB::table('mail_domains')->where('id', $data['domain_id'])->first();
        $email = strtolower($data['local']) . '@' . $dom->name;
        if (DB::table('mail_accounts')->where('email', $email)->exists()) {
            return back()->withErrors(['local' => 'Cet email existe déjà.']);
        }
        $hash = '{BLF-CRYPT}' . password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        DB::table('mail_accounts')->insert([
            'domain_id'    => $data['domain_id'],
            'email'        => $email,
            'password'     => $hash,
            'display_name' => $data['display_name'] ?? null,
            'quota_bytes'  => ($data['quota_mb'] ?? 5120) * 1024 * 1024,
            'maildir'      => $dom->name . '/' . strtolower($data['local']) . '/',
            'active'       => true,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        return redirect('/admin/accounts')->with('success', "Compte $email créé.");
    }

    public function toggleAccount(Request $request, int $id)
    {
        $this->authorize_admin($request);
        $row = DB::table('mail_accounts')->where('id', $id)->first();
        abort_unless($row, 404);
        DB::table('mail_accounts')->where('id', $id)->update([
            'active'     => ! $row->active,
            'updated_at' => now(),
        ]);
        return back()->with('success', $row->active ? 'Compte suspendu.' : 'Compte réactivé.');
    }

    public function deleteAccount(Request $request, int $id)
    {
        $this->authorize_admin($request);
        DB::table('mail_accounts')->where('id', $id)->delete();
        return back()->with('success', 'Compte supprimé.');
    }

    public function resetPassword(Request $request, int $id)
    {
        $this->authorize_admin($request);
        $data = $request->validate(['password' => 'required|string|min:10|max:200']);
        $hash = '{BLF-CRYPT}' . password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        DB::table('mail_accounts')->where('id', $id)->update([
            'password'   => $hash,
            'updated_at' => now(),
        ]);
        return back()->with('success', 'Mot de passe réinitialisé.');
    }

    /** Affiche les enregistrements DNS attendus pour un domaine. */
    public function domainDns(Request $request, int $id)
    {
        $this->authorize_admin($request);
        $dom = DB::table('mail_domains')->where('id', $id)->first();
        abort_unless($dom, 404);
        $serverIp = trim(@file_get_contents('https://api.ipify.org') ?: '<IP-SERVEUR>');
        // Lecture clé DKIM si présente
        $dkimPubPath = "/etc/dkim/{$dom->name}.pub";
        $dkimPub = is_readable($dkimPubPath) ? trim(file_get_contents($dkimPubPath)) : null;
        return Inertia::render('Admin/DomainDns', [
            'domain'    => $dom,
            'server_ip' => $serverIp,
            'dkim_pub'  => $dkimPub,
        ]);
    }
}
