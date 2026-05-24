<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Services\AppSettings;
use App\Services\AuditLog;
use App\Services\ImapMigrationRunner;
use App\Services\PostfixQueue;
use App\Services\RspamdClient;
use Illuminate\Support\Facades\Crypt;
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
        $admin = strtolower((string) AppSettings::get('admin_email', env('ADMIN_EMAIL', '')));
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
            'admin_email' => AppSettings::get('admin_email', env('ADMIN_EMAIL')),
            'app_domain'  => config('mail.primary_domain'),
        ]);
    }

    public function domains(Request $request)
    {
        $this->authorize_admin($request);
        $domains = DB::table('mail_domains')
            ->leftJoin('mail_accounts', 'mail_accounts.domain_id', '=', 'mail_domains.id')
            ->selectRaw('mail_domains.*, COUNT(mail_accounts.id) as accounts_count')
            ->groupBy(
                'mail_domains.id','mail_domains.name','mail_domains.active','mail_domains.is_primary',
                'mail_domains.organization_id','mail_domains.created_at','mail_domains.updated_at'
            )
            ->orderByDesc('mail_domains.is_primary')
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
        // Rattache à l'organisation de l'admin courant. Le contexte est posé
        // par EnsureAdmin middleware ; fallback : 1ère orga par défaut.
        $ctx = $request->attributes->get('org_ctx') ?? \App\Services\OrgContext::current($request);
        $orgId = $ctx['org']?->id ?? DB::table('organizations')->min('id');

        DB::table('mail_domains')->insert([
            'name'            => $name,
            'active'          => true,
            'organization_id' => $orgId,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
        AuditLog::record($request, 'domain.create', $name, ['organization_id' => $orgId]);
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
        AuditLog::record($request, 'domain.delete', (string) $id);
        return back()->with('success', 'Domaine supprimé.');
    }

    public function accounts(Request $request)
    {
        $this->authorize_admin($request);
        $accounts = DB::table('mail_accounts')
            ->join('mail_domains','mail_domains.id','=','mail_accounts.domain_id')
            ->select(
                'mail_accounts.id','mail_accounts.email','mail_accounts.display_name',
                'mail_accounts.first_name','mail_accounts.last_name',
                'mail_accounts.phone','mail_accounts.mobile',
                'mail_accounts.job_title','mail_accounts.company',
                'mail_accounts.active','mail_accounts.quota_bytes','mail_accounts.domain_id',
                'mail_accounts.created_at',
                'mail_domains.name as domain_name'
            )
            ->orderBy('mail_accounts.email')->get()
            ->map(function ($a) {
                $a->local = explode('@', $a->email, 2)[0] ?? '';
                $a->quota_mb = intdiv((int) $a->quota_bytes, 1024 * 1024);
                return $a;
            })->toArray();
        $domains = DB::table('mail_domains')->where('active', true)
            ->orderByDesc('is_primary')->orderBy('name')
            ->get(['id','name','is_primary'])->toArray();
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
            'quota_mb'     => 'nullable|integer|min:10|max:' . AppSettings::int('max_quota_mb', 51200),
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
            'quota_bytes'  => ($data['quota_mb'] ?? AppSettings::int('default_quota_mb', 5120)) * 1024 * 1024,
            'maildir'      => $dom->name . '/' . strtolower($data['local']) . '/',
            'active'       => true,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        AuditLog::record($request, 'account.create', $email, ['quota_mb' => $data['quota_mb'] ?? null]);
        return redirect('/admin/accounts')->with('success', "Compte $email créé.");
    }

    public function toggleAccount(Request $request, int $id)
    {
        $this->authorize_admin($request);
        $row = DB::table('mail_accounts')->where('id', $id)->first();
        abort_unless($row, 404);
        // Anti-lockout : refuse de suspendre/désactiver l'email admin lui-même.
        $adminEmail = strtolower((string) AppSettings::get('admin_email', env('ADMIN_EMAIL', '')));
        if (strtolower($row->email) === $adminEmail && $row->active) {
            return back()->withErrors(['email' => 'Tu ne peux pas suspendre le compte administrateur (lockout).']);
        }
        DB::table('mail_accounts')->where('id', $id)->update([
            'active'     => ! $row->active,
            'updated_at' => now(),
        ]);
        AuditLog::record($request, $row->active ? 'account.suspend' : 'account.activate', $row->email);
        return back()->with('success', $row->active ? 'Compte suspendu.' : 'Compte réactivé.');
    }

    /**
     * PATCH /admin/accounts/{id} — édition complète d'un compte :
     * email (local + domain), display_name, profil étendu, quota.
     *
     * Change l'email = renomme le maildir Dovecot. On utilise une
     * transaction pour éviter un état incohérent.
     */
    public function updateAccount(Request $request, int $id)
    {
        $this->authorize_admin($request);
        $row = DB::table('mail_accounts')->where('id', $id)->first();
        abort_unless($row, 404);

        $max = AppSettings::int('max_quota_mb', 51200);
        $data = $request->validate([
            'local'        => 'required|string|max:64|regex:/^[a-z0-9][a-z0-9._-]{1,63}$/i',
            'domain_id'    => 'required|integer|exists:mail_domains,id',
            'display_name' => 'nullable|string|max:120',
            'first_name'   => 'nullable|string|max:80',
            'last_name'    => 'nullable|string|max:80',
            'phone'        => 'nullable|string|max:32',
            'mobile'       => 'nullable|string|max:32',
            'job_title'    => 'nullable|string|max:120',
            'company'      => 'nullable|string|max:120',
            'quota_mb'     => "required|integer|min:10|max:$max",
        ]);

        $newDomain = DB::table('mail_domains')->where('id', $data['domain_id'])->first();
        abort_unless($newDomain, 404, 'Domaine introuvable');
        $newEmail = strtolower($data['local']) . '@' . $newDomain->name;

        // Anti-lockout : on n'autorise pas à modifier l'email de l'admin courant
        // (sinon il perd l'accès à son propre admin).
        $meEmail = strtolower((string) $request->session()->get('mail_user', ''));
        if (strtolower($row->email) === $meEmail && $newEmail !== strtolower($row->email)) {
            return back()->withErrors(['local' => 'Tu ne peux pas changer ton propre email depuis l\'admin (déconnexion garantie). Utilise un autre compte owner.']);
        }
        if ($newEmail !== strtolower($row->email)
            && DB::table('mail_accounts')->where('email', $newEmail)->exists()) {
            return back()->withErrors(['local' => 'Un compte existe déjà avec cet email.']);
        }

        $newMaildir = $newDomain->name . '/' . strtolower($data['local']) . '/';

        DB::transaction(function () use ($id, $newEmail, $data, $newMaildir, $row) {
            DB::table('mail_accounts')->where('id', $id)->update([
                'email'        => $newEmail,
                'domain_id'    => $data['domain_id'],
                'display_name' => $data['display_name'] ?? null,
                'first_name'   => $data['first_name'] ?? null,
                'last_name'    => $data['last_name'] ?? null,
                'phone'        => $data['phone'] ?? null,
                'mobile'       => $data['mobile'] ?? null,
                'job_title'    => $data['job_title'] ?? null,
                'company'      => $data['company'] ?? null,
                'quota_bytes'  => (int) $data['quota_mb'] * 1024 * 1024,
                'maildir'      => $newMaildir,
                'updated_at'   => now(),
            ]);
            // Si l'email change, l'organisation_contacts membre est resynchro
            // au prochain load /contacts (la sync supprime les rows orphelines).
        });

        // Renommage physique du maildir si le chemin a bougé. Best-effort :
        // si on n'a pas accès au volume vmail depuis le container web,
        // un admin devra faire `doveadm mailbox rename` côté Dovecot.
        if ($row->maildir && $row->maildir !== $newMaildir) {
            $oldPath = '/var/vmail/' . ltrim($row->maildir, '/');
            $newPath = '/var/vmail/' . ltrim($newMaildir, '/');
            if (is_dir($oldPath) && ! is_dir($newPath)) {
                @mkdir(dirname($newPath), 0700, true);
                @rename($oldPath, $newPath);
            }
        }

        AuditLog::record($request, 'account.update', $row->email, [
            'new_email' => $newEmail,
            'old_quota_mb' => intdiv((int) $row->quota_bytes, 1024 * 1024),
            'new_quota_mb' => $data['quota_mb'],
        ]);
        return back()->with('success', "Compte mis à jour : $newEmail");
    }

    /**
     * PATCH /admin/domains/{id}/primary — marque ce domaine comme primaire
     * pour l'organisation. Tous les autres deviennent secondaires.
     */
    public function setPrimaryDomain(Request $request, int $id)
    {
        $this->authorize_admin($request);
        $dom = DB::table('mail_domains')->where('id', $id)->first();
        abort_unless($dom, 404);

        DB::transaction(function () use ($dom) {
            DB::table('mail_domains')
                ->where('organization_id', $dom->organization_id)
                ->update(['is_primary' => false, 'updated_at' => now()]);
            DB::table('mail_domains')->where('id', $dom->id)
                ->update(['is_primary' => true, 'updated_at' => now()]);
        });

        AuditLog::record($request, 'domain.set_primary', $dom->name);
        return back()->with('success', "Domaine primaire : {$dom->name}");
    }

    /** PATCH /admin/accounts/{id}/quota — modifie le quota d'un compte. */
    public function updateQuota(Request $request, int $id)
    {
        $this->authorize_admin($request);
        $max = AppSettings::int('max_quota_mb', 51200);
        $data = $request->validate([
            'quota_mb' => "required|integer|min:10|max:$max",
        ]);
        $row = DB::table('mail_accounts')->where('id', $id)->first();
        abort_unless($row, 404);

        $newBytes = $data['quota_mb'] * 1024 * 1024;
        DB::table('mail_accounts')->where('id', $id)->update([
            'quota_bytes' => $newBytes,
            'updated_at'  => now(),
        ]);

        // Tente de supprimer le fichier maildirsize cache par Dovecot.
        // (chemin standard /var/vmail/<domain>/<local>/Maildir/maildirsize ;
        // si la stack utilise un volume partage avec le web container c'est OK,
        // sinon il faudra lancer `doveadm quota recalc -u $email` cote dovecot)
        $maildirsize = '/var/vmail/' . ltrim($row->maildir, '/') . 'Maildir/maildirsize';
        if (is_writable($maildirsize)) @unlink($maildirsize);

        AuditLog::record($request, 'account.quota_update', $row->email, ['new_quota_mb' => $data['quota_mb']]);
        return back()->with('success',
            "Quota mis a jour ({$data['quota_mb']} Mo). " .
            "L'utilisateur doit se deconnecter/reconnecter pour appliquer la nouvelle limite."
        );
    }

    public function deleteAccount(Request $request, int $id)
    {
        $this->authorize_admin($request);
        $row = DB::table('mail_accounts')->where('id', $id)->first();
        abort_unless($row, 404);
        $adminEmail = strtolower((string) AppSettings::get('admin_email', env('ADMIN_EMAIL', '')));
        if (strtolower($row->email) === $adminEmail) {
            return back()->withErrors(['email' => 'Tu ne peux pas supprimer le compte administrateur (lockout).']);
        }
        AuditLog::record($request, 'account.delete', $row->email);
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
        AuditLog::record($request, 'account.password_reset', (string) $id);
        return back()->with('success', 'Mot de passe réinitialisé.');
    }

    /* ──────────────── Mailing lists (listes de diffusion) ──────────────── */

    public function lists(Request $request)
    {
        $this->authorize_admin($request);
        $lists = DB::table('mail_lists')
            ->join('mail_domains', 'mail_domains.id', '=', 'mail_lists.domain_id')
            ->leftJoin('mail_list_members', 'mail_list_members.list_id', '=', 'mail_lists.id')
            ->selectRaw('mail_lists.*, mail_domains.name as domain_name, COUNT(mail_list_members.id) as members_count')
            ->groupBy('mail_lists.id','mail_lists.domain_id','mail_lists.address','mail_lists.display_name','mail_lists.scope','mail_lists.active','mail_lists.created_at','mail_lists.updated_at','mail_domains.name')
            ->orderBy('mail_lists.address')
            ->get()->toArray();
        $domains = DB::table('mail_domains')->where('active', true)->orderBy('name')->get(['id','name'])->toArray();
        return Inertia::render('Admin/Lists', [
            'lists'   => $lists,
            'domains' => $domains,
        ]);
    }

    public function createList(Request $request)
    {
        $this->authorize_admin($request);
        $data = $request->validate([
            'local'        => 'required|string|max:64|regex:/^[a-z0-9][a-z0-9._-]{0,63}$/i',
            'domain_id'    => 'required|integer|exists:mail_domains,id',
            'display_name' => 'nullable|string|max:200',
            'scope'        => 'required|in:internal,internal_domains,any',
        ]);
        $dom = DB::table('mail_domains')->where('id', $data['domain_id'])->first();
        $address = strtolower($data['local']) . '@' . $dom->name;
        if (DB::table('mail_lists')->where('address', $address)->exists()
            || DB::table('mail_accounts')->where('email', $address)->exists()
            || DB::table('mail_aliases')->where('source', $address)->exists()) {
            return back()->withErrors(['local' => 'Cette adresse est déjà utilisée (compte, alias ou liste).']);
        }
        DB::table('mail_lists')->insert([
            'domain_id'    => $data['domain_id'],
            'address'      => $address,
            'display_name' => $data['display_name'] ?? null,
            'scope'        => $data['scope'],
            'active'       => true,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        AuditLog::record($request, 'list.create', $address, ['scope' => $data['scope']]);
        return redirect("/admin/lists")->with('success', "Liste $address créée.");
    }

    public function showList(Request $request, int $id)
    {
        $this->authorize_admin($request);
        $list = DB::table('mail_lists')
            ->join('mail_domains', 'mail_domains.id', '=', 'mail_lists.domain_id')
            ->where('mail_lists.id', $id)
            ->select('mail_lists.*', 'mail_domains.name as domain_name')
            ->first();
        abort_unless($list, 404);
        $members = DB::table('mail_list_members')->where('list_id', $id)
            ->orderBy('member_email')->get()->toArray();
        return Inertia::render('Admin/ListDetail', [
            'list'    => $list,
            'members' => $members,
        ]);
    }

    public function addListMember(Request $request, int $id)
    {
        $this->authorize_admin($request);
        $list = DB::table('mail_lists')->where('id', $id)->first();
        abort_unless($list, 404);
        $data = $request->validate(['member_email' => 'required|email:rfc|max:320']);
        $email = strtolower(trim($data['member_email']));
        if (DB::table('mail_list_members')->where('list_id', $id)->where('member_email', $email)->exists()) {
            return back()->withErrors(['member_email' => 'Membre déjà présent.']);
        }
        DB::table('mail_list_members')->insert([
            'list_id'      => $id,
            'member_email' => $email,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        return back()->with('success', "$email ajouté à la liste.");
    }

    public function removeListMember(Request $request, int $id, int $memberId)
    {
        $this->authorize_admin($request);
        DB::table('mail_list_members')->where('list_id', $id)->where('id', $memberId)->delete();
        return back()->with('success', 'Membre retiré.');
    }

    public function updateListScope(Request $request, int $id)
    {
        $this->authorize_admin($request);
        $data = $request->validate(['scope' => 'required|in:internal,internal_domains,any']);
        DB::table('mail_lists')->where('id', $id)->update([
            'scope' => $data['scope'], 'updated_at' => now(),
        ]);
        return back()->with('success', 'Mode d\'accès mis à jour.');
    }

    public function toggleList(Request $request, int $id)
    {
        $this->authorize_admin($request);
        $row = DB::table('mail_lists')->where('id', $id)->first();
        abort_unless($row, 404);
        DB::table('mail_lists')->where('id', $id)->update([
            'active' => ! $row->active, 'updated_at' => now(),
        ]);
        return back()->with('success', $row->active ? 'Liste désactivée.' : 'Liste activée.');
    }

    public function deleteList(Request $request, int $id)
    {
        $this->authorize_admin($request);
        DB::table('mail_lists')->where('id', $id)->delete();
        AuditLog::record($request, 'list.delete', (string) $id);
        return redirect('/admin/lists')->with('success', 'Liste supprimée.');
    }

    /* ──────────────── Shared mailboxes (boîtes partagées) ──────────────── */

    public function sharedMailboxes(Request $request)
    {
        $this->authorize_admin($request);
        $mboxes = DB::table('shared_mailboxes')
            ->join('mail_domains', 'mail_domains.id', '=', 'shared_mailboxes.domain_id')
            ->leftJoin('shared_mailbox_acls', 'shared_mailbox_acls.shared_mailbox_id', '=', 'shared_mailboxes.id')
            ->selectRaw('shared_mailboxes.*, mail_domains.name as domain_name, COUNT(shared_mailbox_acls.id) as members_count')
            ->groupBy('shared_mailboxes.id','shared_mailboxes.domain_id','shared_mailboxes.email','shared_mailboxes.display_name','shared_mailboxes.quota_bytes','shared_mailboxes.maildir','shared_mailboxes.active','shared_mailboxes.created_at','shared_mailboxes.updated_at','mail_domains.name')
            ->orderBy('shared_mailboxes.email')->get()->toArray();
        $domains = DB::table('mail_domains')->where('active', true)->orderBy('name')->get(['id','name'])->toArray();
        return Inertia::render('Admin/SharedMailboxes', [
            'mailboxes' => $mboxes,
            'domains'   => $domains,
        ]);
    }

    public function createSharedMailbox(Request $request)
    {
        $this->authorize_admin($request);
        $data = $request->validate([
            'local'        => 'required|string|max:64|regex:/^[a-z0-9][a-z0-9._-]{0,63}$/i',
            'domain_id'    => 'required|integer|exists:mail_domains,id',
            'display_name' => 'required|string|max:200',
            'quota_mb'     => 'nullable|integer|min:100|max:' . AppSettings::int('max_quota_mb', 51200),
        ]);
        $dom = DB::table('mail_domains')->where('id', $data['domain_id'])->first();
        $email = strtolower($data['local']) . '@' . $dom->name;
        if (DB::table('shared_mailboxes')->where('email', $email)->exists()
            || DB::table('mail_accounts')->where('email', $email)->exists()
            || DB::table('mail_lists')->where('address', $email)->exists()) {
            return back()->withErrors(['local' => 'Cette adresse est déjà utilisée.']);
        }
        // On crée AUSSI une entrée dans mail_accounts pour que Postfix accepte
        // la livraison (recipient_check sur mail_accounts) et que Dovecot ait
        // un Maildir. Le mot de passe est aléatoire — personne ne se logue
        // directement, on passe par l'identity switcher (master_user).
        $randomPass = '{BLF-CRYPT}' . password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT, ['cost' => 12]);
        $maildir = $dom->name . '/' . strtolower($data['local']) . '/';
        $quotaBytes = ($data['quota_mb'] ?? 10240) * 1024 * 1024;
        DB::beginTransaction();
        try {
            DB::table('mail_accounts')->insert([
                'domain_id' => $data['domain_id'], 'email' => $email, 'password' => $randomPass,
                'display_name' => $data['display_name'], 'quota_bytes' => $quotaBytes,
                'maildir' => $maildir, 'active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('shared_mailboxes')->insert([
                'domain_id' => $data['domain_id'], 'email' => $email,
                'display_name' => $data['display_name'], 'quota_bytes' => $quotaBytes,
                'maildir' => $maildir, 'active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['local' => 'Échec création : ' . $e->getMessage()]);
        }
        AuditLog::record($request, 'shared.create', $email);
        return redirect('/admin/shared')->with('success', "Boîte partagée $email créée.");
    }

    public function showSharedMailbox(Request $request, int $id)
    {
        $this->authorize_admin($request);
        $mbox = DB::table('shared_mailboxes')
            ->join('mail_domains','mail_domains.id','=','shared_mailboxes.domain_id')
            ->where('shared_mailboxes.id', $id)
            ->select('shared_mailboxes.*','mail_domains.name as domain_name')
            ->first();
        abort_unless($mbox, 404);
        $acls = DB::table('shared_mailbox_acls')->where('shared_mailbox_id', $id)
            ->orderBy('user_email')->get()->toArray();
        return Inertia::render('Admin/SharedMailboxDetail', [
            'mailbox' => $mbox,
            'acls'    => $acls,
        ]);
    }

    public function grantSharedAccess(Request $request, int $id)
    {
        $this->authorize_admin($request);
        $data = $request->validate([
            'user_email' => 'required|email:rfc|max:320',
            'role'       => 'required|in:read,send,manage',
        ]);
        $email = strtolower(trim($data['user_email']));
        if (DB::table('shared_mailbox_acls')->where('shared_mailbox_id', $id)
                ->where('user_email', $email)->exists()) {
            return back()->withErrors(['user_email' => 'Cet utilisateur a déjà accès.']);
        }
        DB::table('shared_mailbox_acls')->insert([
            'shared_mailbox_id' => $id, 'user_email' => $email, 'role' => $data['role'],
            'created_at' => now(), 'updated_at' => now(),
        ]);
        AuditLog::record($request, 'shared.grant', $email, ['role' => $data['role'], 'mailbox_id' => $id]);
        return back()->with('success', "$email a maintenant l'accès ({$data['role']}).");
    }

    public function revokeSharedAccess(Request $request, int $id, int $aclId)
    {
        $this->authorize_admin($request);
        AuditLog::record($request, 'shared.revoke', (string) $aclId, ['mailbox_id' => $id]);
        DB::table('shared_mailbox_acls')->where('shared_mailbox_id', $id)->where('id', $aclId)->delete();
        return back()->with('success', 'Accès révoqué.');
    }

    public function deleteSharedMailbox(Request $request, int $id)
    {
        $this->authorize_admin($request);
        $mbox = DB::table('shared_mailboxes')->where('id', $id)->first();
        abort_unless($mbox, 404);
        DB::beginTransaction();
        try {
            DB::table('shared_mailboxes')->where('id', $id)->delete();
            DB::table('mail_accounts')->where('email', $mbox->email)->delete();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['email' => 'Échec suppression : ' . $e->getMessage()]);
        }
        return redirect('/admin/shared')->with('success', 'Boîte partagée supprimée.');
    }

    /* ──────────────── Forwards / Aliases ──────────────── */

    public function aliases(Request $request)
    {
        $this->authorize_admin($request);
        $aliases = DB::table('mail_aliases')
            ->join('mail_domains', 'mail_domains.id', '=', 'mail_aliases.domain_id')
            ->orderBy('mail_aliases.source')
            ->get(['mail_aliases.id','mail_aliases.source','mail_aliases.destination',
                   'mail_aliases.active','mail_aliases.created_at','mail_domains.name as domain_name'])
            ->toArray();
        $domains = DB::table('mail_domains')->where('active', true)
            ->orderBy('name')->get(['id','name'])->toArray();
        return Inertia::render('Admin/Aliases', [
            'aliases' => $aliases,
            'domains' => $domains,
        ]);
    }

    public function createAlias(Request $request)
    {
        $this->authorize_admin($request);
        $data = $request->validate([
            'source_local' => 'required|string|max:64|regex:/^[a-z0-9][a-z0-9._-]{0,63}$/i',
            'domain_id'    => 'required|integer|exists:mail_domains,id',
            'destination'  => 'required|email:rfc|max:320',
        ]);
        $dom = DB::table('mail_domains')->where('id', $data['domain_id'])->first();
        $source = strtolower($data['source_local']) . '@' . $dom->name;
        $dest   = strtolower(trim($data['destination']));

        // Boucle évidente : alias renvoie vers lui-même
        if ($source === $dest) {
            return back()->withErrors(['destination' => 'La destination doit être différente de la source.']);
        }
        // Pas de doublon strict
        $exists = DB::table('mail_aliases')->where('source', $source)->where('destination', $dest)->exists();
        if ($exists) {
            return back()->withErrors(['source_local' => 'Ce forward existe déjà.']);
        }
        DB::table('mail_aliases')->insert([
            'domain_id'   => $data['domain_id'],
            'source'      => $source,
            'destination' => $dest,
            'active'      => true,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        AuditLog::record($request, 'alias.create', $source, ['destination' => $dest]);
        return back()->with('success', "Forward $source → $dest créé.");
    }

    public function toggleAlias(Request $request, int $id)
    {
        $this->authorize_admin($request);
        $row = DB::table('mail_aliases')->where('id', $id)->first();
        abort_unless($row, 404);
        DB::table('mail_aliases')->where('id', $id)->update([
            'active'     => ! $row->active,
            'updated_at' => now(),
        ]);
        return back()->with('success', $row->active ? 'Forward désactivé.' : 'Forward activé.');
    }

    public function deleteAlias(Request $request, int $id)
    {
        $this->authorize_admin($request);
        AuditLog::record($request, 'alias.delete', (string) $id);
        DB::table('mail_aliases')->where('id', $id)->delete();
        return back()->with('success', 'Forward supprimé.');
    }

    /** Affiche les enregistrements DNS attendus pour un domaine. */
    public function domainDns(Request $request, int $id)
    {
        $this->authorize_admin($request);
        $dom = DB::table('mail_domains')->where('id', $id)->first();
        abort_unless($dom, 404);
        // Cache 24h pour éviter de marteler ipify à chaque load de la page DNS.
        $serverIp = \Illuminate\Support\Facades\Cache::remember('server_public_ip', 86400, function () {
            $ctx = stream_context_create(['http' => ['timeout' => 3]]);
            $ip  = @file_get_contents('https://api.ipify.org', false, $ctx);
            return trim((string) $ip) ?: '<IP-SERVEUR>';
        });
        // Lecture clé DKIM si présente
        // Lecture clé DKIM publique : on essaie plusieurs chemins (opendkim, rspamd, env).
        $candidates = [
            "/etc/opendkim/keys/{$dom->name}/mail.txt",     // opendkim-genkey
            "/etc/rspamd/dkim/{$dom->name}.pub",            // rspamd dkim_signing
            "/var/lib/rspamd/dkim/{$dom->name}.mail.pub",   // rspamd alt path
            "/etc/dkim/{$dom->name}.pub",                   // fallback historique
        ];
        $dkimPub = null;
        foreach ($candidates as $path) {
            if (is_readable($path)) {
                $raw = (string) file_get_contents($path);
                // opendkim format = TXT record entier sur plusieurs lignes :
                //   mail._domainkey IN TXT ( "v=DKIM1; ..." "p=AB..." )
                // On extrait juste le contenu des quotes.
                if (preg_match_all('/"([^"]+)"/', $raw, $m)) {
                    $dkimPub = implode('', $m[1]);
                } else {
                    $dkimPub = trim(preg_replace('/[\s\r\n]+/', '', $raw));
                }
                break;
            }
        }
        return Inertia::render('Admin/DomainDns', [
            'domain'    => $dom,
            'server_ip' => $serverIp,
            'dkim_pub'  => $dkimPub,
        ]);
    }
    public function settings(Request $request)
    {
        $this->authorize_admin($request);
        return Inertia::render('Admin/Settings', [
            'settings'    => AppSettings::all(),
            'app_domain'  => config('mail.primary_domain'),
            'admin_email' => AppSettings::get('admin_email', env('ADMIN_EMAIL')),
        ]);
    }

    public function saveSettings(Request $request)
    {
        $this->authorize_admin($request);
        $data = $request->validate([
            'allow_registration' => 'required|boolean',
            'default_quota_mb'   => 'required|integer|min:10',
            'max_quota_mb'       => 'required|integer|min:100',
            'backup_enabled'     => 'required|boolean',
            'backup_hour_utc'    => 'required|integer|min:0|max:23',
            'enable_noliae_ai'   => 'required|boolean',
            'noliae_ai_api_key'  => 'nullable|string|max:200',
            'admin_email'        => 'required|email|max:320',
        ]);
        if (! empty($data['enable_noliae_ai']) && empty($data['noliae_ai_api_key'])) {
            return back()->withErrors(['noliae_ai_api_key' => 'Une cle API Noliae est requise pour activer l\'integration IA.']);
        }
        if ($data['default_quota_mb'] > $data['max_quota_mb']) {
            return back()->withErrors(['default_quota_mb' => 'Le quota par défaut ne peut pas dépasser le maximum.']);
        }
        foreach ($data as $key => $value) {
            AppSettings::set($key, is_bool($value) ? ($value ? '1' : '0') : (string) $value);
        }
        AuditLog::record($request, 'settings.update', null, array_keys($data));
        return back()->with('success', 'Paramètres enregistrés.');
    }


    /* ──────────────── Migrations IMAP (imapsync) ──────────────── */

    public function migrations(Request $request, ImapMigrationRunner $runner)
    {
        $this->authorize_admin($request);
        // Refresh status des jobs en cours
        DB::table('mail_migrations')->whereIn('status', ['pending', 'running'])
            ->pluck('id')->each(fn ($id) => $runner->refreshStatus($id));

        $migrations = DB::table('mail_migrations')
            ->leftJoin('mail_accounts', 'mail_accounts.id', '=', 'mail_migrations.dest_account_id')
            ->select('mail_migrations.*', 'mail_accounts.email as dest_email')
            ->orderByDesc('mail_migrations.created_at')->limit(50)
            ->get()->toArray();

        $accounts = DB::table('mail_accounts')->where('active', true)
            ->orderBy('email')->get(['id', 'email'])->toArray();

        return Inertia::render('Admin/Migrations', [
            'migrations'         => $migrations,
            'accounts'           => $accounts,
            'imapsync_available' => $runner->imapsyncAvailable(),
        ]);
    }

    public function startMigration(Request $request, ImapMigrationRunner $runner)
    {
        $this->authorize_admin($request);
        $data = $request->validate([
            'source_host'     => 'required|string|max:253',
            'source_port'     => 'required|integer|min:1|max:65535',
            'source_ssl'      => 'required|boolean',
            'source_user'     => 'required|string|max:320',
            'source_pass'     => 'required|string|max:500',
            'dest_account_id' => 'required|integer|exists:mail_accounts,id',
        ]);

        $id = DB::table('mail_migrations')->insertGetId([
            'source_host'      => $data['source_host'],
            'source_port'      => $data['source_port'],
            'source_ssl'       => $data['source_ssl'],
            'source_user'      => $data['source_user'],
            'source_pass_enc'  => Crypt::encryptString($data['source_pass']),
            'dest_account_id'  => $data['dest_account_id'],
            'status'           => 'pending',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $runner->start($id);
        AuditLog::record($request, 'migration.start', $data['source_user'],
            ['dest_account_id' => $data['dest_account_id'], 'source_host' => $data['source_host']]);

        return redirect('/admin/migrations')->with('success', "Migration #$id démarrée.");
    }

    public function cancelMigration(Request $request, int $id, ImapMigrationRunner $runner)
    {
        $this->authorize_admin($request);
        $runner->cancel($id);
        AuditLog::record($request, 'migration.cancel', (string) $id);
        return back()->with('success', 'Migration annulée.');
    }

    public function migrationLog(Request $request, int $id, ImapMigrationRunner $runner)
    {
        $this->authorize_admin($request);
        $runner->refreshStatus($id);
        $job = DB::table('mail_migrations')->where('id', $id)->first();
        abort_unless($job, 404);
        return response()->json([
            'status'  => $job->status,
            'copied'  => $job->copied_messages,
            'total'   => $job->total_messages,
            'log'     => $job->log_tail,
            'error'   => $job->error,
        ]);
    }

    /* ──────────────── Quarantaine spam (Postfix HOLD queue) ──────────────── */

    public function spamQueue(Request $request, PostfixQueue $pq)
    {
        $this->authorize_admin($request);
        return Inertia::render('Admin/Spam', [
            'available' => $pq->available(),
            'held'      => $pq->holdList(),
        ]);
    }

    public function spamShow(Request $request, string $queueId, PostfixQueue $pq)
    {
        $this->authorize_admin($request);
        if (! preg_match('/^[A-F0-9]+$/i', $queueId)) abort(400);
        // 404 si le mail n'existe plus dans la queue (au lieu d'un 200 vide
        // qui masque le bug côté UI).
        $body = $pq->showMail($queueId);
        if ($body === null || $body === '') abort(404);
        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }

    public function spamRelease(Request $request, string $queueId, PostfixQueue $pq)
    {
        $this->authorize_admin($request);
        if (! preg_match('/^[A-F0-9]+$/i', $queueId)) abort(400);
        $ok = $pq->release($queueId);
        AuditLog::record($request, 'spam.release', $queueId);
        return back()->with($ok ? 'success' : 'error',
            $ok ? "Mail $queueId libéré → livré au destinataire."
                : "Échec libération.");
    }

    public function spamDelete(Request $request, string $queueId, PostfixQueue $pq)
    {
        $this->authorize_admin($request);
        if (! preg_match('/^[A-F0-9]+$/i', $queueId)) abort(400);
        $ok = $pq->delete($queueId);
        AuditLog::record($request, 'spam.delete', $queueId);
        return back()->with($ok ? 'success' : 'error',
            $ok ? "Mail $queueId supprimé." : "Échec suppression.");
    }

    public function spamReleaseAll(Request $request, PostfixQueue $pq)
    {
        $this->authorize_admin($request);
        $ok = $pq->releaseAll();
        AuditLog::record($request, 'spam.release_all', null);
        return back()->with($ok ? 'success' : 'error',
            $ok ? 'Tous les mails HOLD libérés.' : 'Échec libération en masse.');
    }

    /* ──────────────── Rspamd metrics ──────────────── */

    public function rspamd(Request $request, RspamdClient $r)
    {
        $this->authorize_admin($request);
        return Inertia::render('Admin/Rspamd', [
            'available' => $r->available(),
            'stat'      => $r->get('/stat'),
            'actions'   => $r->get('/actions'),
            'history'   => $r->get('/history')['rows'] ?? [],
            'errors'    => $r->get('/errors')['errors'] ?? [],
            'symbols'   => $this->topSymbols($r),
            'rspamd_url' => env('RSPAMD_URL', 'http://127.0.0.1:11334'),
        ]);
    }

    private function topSymbols(RspamdClient $r): array
    {
        $all = $r->get('/symbols') ?? [];
        // L'API rspamd renvoie selon les versions :
        //   - une liste plate [ {group, rules:[...]} , ... ]   (3.x récent)
        //   - un dict { group => {rules:[...]} }              (3.x ancien)
        //   - parfois directement [ {symbol, weight, ...}, ... ]
        // On supporte les 3 formes.
        $flat = [];
        foreach ($all as $key => $items) {
            if (! is_array($items)) continue;
            // Forme C : entrée plate "symbole"
            if (isset($items['symbol']) || isset($items['weight'])) {
                $flat[] = [
                    'name'   => $items['symbol'] ?? (is_string($key) ? $key : ''),
                    'group'  => $items['group'] ?? '',
                    'weight' => (float) ($items['weight'] ?? 0),
                    'desc'   => $items['description'] ?? '',
                ];
                continue;
            }
            // Formes A / B : groupe -> rules[]
            $group = is_string($key) ? $key : (string) ($items['group'] ?? '');
            foreach ($items['rules'] ?? ($items['symbols'] ?? []) as $rule) {
                if (! is_array($rule)) continue;
                $flat[] = [
                    'name'   => $rule['symbol'] ?? '',
                    'group'  => $group,
                    'weight' => (float) ($rule['weight'] ?? 0),
                    'desc'   => $rule['description'] ?? '',
                ];
            }
        }
        usort($flat, fn ($a, $b) => abs($b['weight']) <=> abs($a['weight']));
        return array_slice($flat, 0, 30);
    }

    /* ──────────────── Audit log ──────────────── */

    public function auditLog(Request $request)
    {
        $this->authorize_admin($request);
        $entries = DB::table('audit_log')
            ->orderByDesc('created_at')
            ->limit(500)
            ->get()->toArray();
        return Inertia::render('Admin/Audit', [
            'entries' => $entries,
        ]);
    }

}
