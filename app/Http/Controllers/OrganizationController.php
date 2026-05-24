<?php

namespace App\Http\Controllers;

use App\Services\AuditLog;
use App\Services\OrgContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Noliae Mail OSS — Organization Management.
 *
 * Routes :
 *   GET  /org                   settings + liste domaines (admin+)
 *   POST /org                   update name/slug (owner only)
 *   GET  /org/members           liste des membres + rôles (admin+)
 *   POST /org/members           ajoute un membre (owner only)
 *   PATCH /org/members/{id}     change un rôle (owner only)
 *   DELETE /org/members/{id}    retire un membre (owner only)
 */
class OrganizationController extends Controller
{
    private function ctx(Request $request): array
    {
        $ctx = $request->attributes->get('org_ctx') ?? OrgContext::current($request);
        abort_unless($ctx['org'], 404, 'Aucune organisation associée.');
        return $ctx;
    }

    public function settings(Request $request)
    {
        $ctx = $this->ctx($request);
        $domains = DB::table('mail_domains')
            ->where('organization_id', $ctx['org']->id)
            ->orderBy('name')->get(['id', 'name', 'active'])->toArray();
        $stats = [
            'members'  => DB::table('organization_members')->where('organization_id', $ctx['org']->id)->count(),
            'domains'  => count($domains),
            'accounts' => DB::table('mail_accounts')
                ->whereIn('domain_id', collect($domains)->pluck('id'))->count(),
        ];
        return Inertia::render('Org/Settings', [
            'org'        => $ctx['org'],
            'role'       => $ctx['role'],
            'domains'    => $domains,
            'stats'      => $stats,
            'logo_url'   => $ctx['org']->logo_path
                ? '/org/logo?v=' . substr(md5((string) $ctx['org']->logo_path), 0, 8)
                : null,
            'branding'   => [
                'footer_label'   => (string) \App\Services\AppSettings::get('login_footer_label', 'github.com/Noliae/noliae-mail-oss'),
                'footer_url'     => (string) \App\Services\AppSettings::get('login_footer_url', 'https://github.com/Noliae/noliae-mail-oss'),
                'footer_tagline' => (string) \App\Services\AppSettings::get('login_footer_tagline', 'Messagerie souveraine, open source'),
            ],
        ]);
    }

    /** POST /org/logo — upload du logo de l'organisation (owner only). */
    public function uploadLogo(Request $request)
    {
        $ctx = $this->ctx($request);
        abort_unless($ctx['role'] === 'owner', 403, 'Seul un owner peut changer le logo.');
        $request->validate([
            'logo' => 'required|file|image|mimes:png,jpeg,webp,svg|max:1024',
        ]);
        $file = $request->file('logo');
        $mime = (string) $file->getMimeType();
        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            default => 'png',
        };
        $dir = storage_path('app/private/org-logos');
        if (! is_dir($dir)) @mkdir($dir, 0750, true);
        $base = 'org-' . $ctx['org']->id;
        foreach (glob($dir . '/' . $base . '.*') ?: [] as $old) { @unlink($old); }
        $rel = 'org-logos/' . $base . '.' . $ext;
        $file->move($dir, $base . '.' . $ext);
        DB::table('organizations')->where('id', $ctx['org']->id)->update([
            'logo_path' => $rel, 'updated_at' => now(),
        ]);
        \App\Services\AuditLog::record($request, 'org.logo.upload', (string) $ctx['org']->id);
        return back()->with('success', 'Logo de l\'organisation mis à jour.');
    }

    public function deleteLogo(Request $request)
    {
        $ctx = $this->ctx($request);
        abort_unless($ctx['role'] === 'owner', 403);
        if ($ctx['org']->logo_path) {
            @unlink(storage_path('app/private/' . $ctx['org']->logo_path));
        }
        DB::table('organizations')->where('id', $ctx['org']->id)->update([
            'logo_path' => null, 'updated_at' => now(),
        ]);
        return back()->with('success', 'Logo retiré.');
    }

    /** GET /org/logo — public (affiché sur les pages de login). */
    public function serveLogo(Request $request)
    {
        $org = DB::table('organizations')->whereNotNull('logo_path')->orderBy('id')->first();
        if (! $org) abort(404);
        $path = storage_path('app/private/' . $org->logo_path);
        if (! is_readable($path)) abort(404);
        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            'svg'         => 'image/svg+xml',
            default       => 'application/octet-stream',
        };
        return response()->file($path, [
            'Content-Type'  => $mime,
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /** POST /org/branding — l'owner change le footer custom des pages login. */
    public function updateBranding(Request $request)
    {
        $ctx = $this->ctx($request);
        abort_unless($ctx['role'] === 'owner', 403);
        $data = $request->validate([
            'footer_label'   => 'nullable|string|max:80',
            'footer_url'     => 'nullable|url|max:255',
            'footer_tagline' => 'nullable|string|max:120',
        ]);
        foreach ($data as $k => $v) {
            \App\Services\AppSettings::set('login_' . $k, (string) ($v ?? ''));
        }
        return back()->with('success', 'Personnalisation des pages de connexion enregistrée.');
    }

    public function updateSettings(Request $request)
    {
        $ctx = $this->ctx($request);
        abort_unless($ctx['role'] === 'owner', 403, 'Seul un owner peut modifier les paramètres.');
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'slug' => 'required|string|max:64|regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/',
        ]);
        // Slug unique sauf soi-même
        $clash = DB::table('organizations')->where('slug', $data['slug'])->where('id', '!=', $ctx['org']->id)->exists();
        if ($clash) {
            return back()->withErrors(['slug' => 'Ce slug est déjà utilisé.']);
        }
        DB::table('organizations')->where('id', $ctx['org']->id)->update([
            'name'       => $data['name'],
            'slug'       => $data['slug'],
            'updated_at' => now(),
        ]);
        AuditLog::record($request, 'org.update', (string) $ctx['org']->id, $data);
        return back()->with('success', 'Organisation mise à jour.');
    }

    public function members(Request $request)
    {
        $ctx = $this->ctx($request);
        $domainIds = DB::table('mail_domains')->where('organization_id', $ctx['org']->id)->pluck('id');
        $members = DB::table('organization_members')
            ->join('mail_accounts', 'mail_accounts.id', '=', 'organization_members.mail_account_id')
            ->where('organization_members.organization_id', $ctx['org']->id)
            ->select(
                'organization_members.id',
                'organization_members.role',
                'organization_members.created_at',
                'mail_accounts.id as account_id',
                'mail_accounts.email',
                'mail_accounts.display_name',
                'mail_accounts.totp_enabled',
                'mail_accounts.active'
            )
            ->orderByRaw("array_position(ARRAY['owner','admin','support','member']::text[], organization_members.role)")
            ->orderBy('mail_accounts.email')
            ->get()
            ->map(function ($m) {
                $m->avatar_hash = md5(strtolower($m->email));
                return $m;
            })->toArray();
        // Liste des comptes de l'orga qui ne sont PAS encore membres (pour add dropdown)
        $availableAccounts = DB::table('mail_accounts')
            ->whereIn('domain_id', $domainIds)
            ->whereNotIn('id', DB::table('organization_members')
                ->where('organization_id', $ctx['org']->id)->pluck('mail_account_id'))
            ->orderBy('email')
            ->get(['id', 'email', 'display_name'])->toArray();
        return Inertia::render('Org/Members', [
            'org'      => $ctx['org'],
            'role'     => $ctx['role'],
            'members'  => $members,
            'available' => $availableAccounts,
            'me_email' => strtolower((string) $request->session()->get('mail_user', '')),
        ]);
    }

    public function addMember(Request $request)
    {
        $ctx = $this->ctx($request);
        abort_unless($ctx['role'] === 'owner', 403, 'Seul un owner peut ajouter un membre.');
        $data = $request->validate([
            'mail_account_id' => 'required|integer|exists:mail_accounts,id',
            'role'            => 'required|in:owner,admin,support,member',
        ]);
        // Le compte doit appartenir à un domaine de l'orga
        $dom = DB::table('mail_accounts')->where('id', $data['mail_account_id'])->value('domain_id');
        $belongs = DB::table('mail_domains')->where('id', $dom)
            ->where('organization_id', $ctx['org']->id)->exists();
        abort_unless($belongs, 422, 'Ce compte n\'appartient pas à un domaine de cette organisation.');

        DB::table('organization_members')->insert([
            'organization_id' => $ctx['org']->id,
            'mail_account_id' => $data['mail_account_id'],
            'role'            => $data['role'],
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
        AuditLog::record($request, 'org.member.add', (string) $data['mail_account_id'], ['role' => $data['role']]);
        return back()->with('success', 'Membre ajouté.');
    }

    public function updateMemberRole(Request $request, int $id)
    {
        $ctx = $this->ctx($request);
        abort_unless($ctx['role'] === 'owner', 403);
        $data = $request->validate(['role' => 'required|in:owner,admin,support,member']);
        $m = DB::table('organization_members')->where('id', $id)
            ->where('organization_id', $ctx['org']->id)->first();
        abort_unless($m, 404);
        // Anti-lockout : interdit de retirer le dernier owner
        if ($m->role === 'owner' && $data['role'] !== 'owner') {
            $ownerCount = DB::table('organization_members')
                ->where('organization_id', $ctx['org']->id)->where('role', 'owner')->count();
            if ($ownerCount <= 1) {
                return back()->withErrors(['role' => 'Impossible : il faut au moins un owner.']);
            }
        }
        DB::table('organization_members')->where('id', $id)->update([
            'role' => $data['role'], 'updated_at' => now(),
        ]);
        AuditLog::record($request, 'org.member.role', (string) $id, ['role' => $data['role']]);
        return back()->with('success', 'Rôle mis à jour.');
    }

    public function removeMember(Request $request, int $id)
    {
        $ctx = $this->ctx($request);
        abort_unless($ctx['role'] === 'owner', 403);
        $m = DB::table('organization_members')->where('id', $id)
            ->where('organization_id', $ctx['org']->id)->first();
        abort_unless($m, 404);
        if ($m->role === 'owner') {
            $ownerCount = DB::table('organization_members')
                ->where('organization_id', $ctx['org']->id)->where('role', 'owner')->count();
            if ($ownerCount <= 1) {
                return back()->withErrors(['role' => 'Impossible : dernier owner.']);
            }
        }
        DB::table('organization_members')->where('id', $id)->delete();
        AuditLog::record($request, 'org.member.remove', (string) $id);
        return back()->with('success', 'Membre retiré.');
    }
}
