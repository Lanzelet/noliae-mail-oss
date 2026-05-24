<?php

namespace App\Http\Controllers;

use App\Services\OrgContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Annuaire des membres de l'organisation (Global Address List type O365).
 *
 * Liste tous les `mail_accounts` ayant un domaine appartenant à l'orga de
 * l'utilisateur courant. Affiche avatar (réel ou SVG initiales), nom, email.
 */
class PeopleController extends Controller
{
    public function index(Request $request)
    {
        $ctx = OrgContext::current($request);
        abort_unless($ctx['org'], 404, 'Pas d\'organisation.');

        $domainIds = DB::table('mail_domains')->where('organization_id', $ctx['org']->id)->pluck('id');
        $q = trim((string) $request->query('q', ''));

        $query = DB::table('mail_accounts')
            ->join('mail_domains', 'mail_domains.id', '=', 'mail_accounts.domain_id')
            ->leftJoin('organization_members', function ($j) use ($ctx) {
                $j->on('organization_members.mail_account_id', '=', 'mail_accounts.id')
                  ->where('organization_members.organization_id', '=', $ctx['org']->id);
            })
            ->whereIn('mail_accounts.domain_id', $domainIds)
            ->where('mail_accounts.active', true)
            ->select(
                'mail_accounts.id',
                'mail_accounts.email',
                'mail_accounts.display_name',
                'mail_accounts.avatar_path',
                'mail_domains.name as domain',
                'organization_members.role'
            );
        if ($q !== '') {
            $like = '%' . str_replace('%', '\\%', $q) . '%';
            $query->where(function ($w) use ($like) {
                $w->whereRaw('LOWER(mail_accounts.email) LIKE LOWER(?)', [$like])
                  ->orWhereRaw('LOWER(COALESCE(mail_accounts.display_name, \'\')) LIKE LOWER(?)', [$like]);
            });
        }
        $people = $query->orderBy('mail_accounts.display_name')->orderBy('mail_accounts.email')
            ->limit(200)->get()->map(function ($p) {
                return [
                    'id'           => $p->id,
                    'email'        => $p->email,
                    'display_name' => $p->display_name,
                    'domain'       => $p->domain,
                    'role'         => $p->role,
                    'has_avatar'   => (bool) $p->avatar_path,
                    'avatar_hash'  => md5(strtolower($p->email)),
                ];
            })->values()->toArray();

        return Inertia::render('People', [
            'org'    => $ctx['org'],
            'people' => $people,
            'q'      => $q,
            'me'     => strtolower((string) $request->session()->get('mail_user', '')),
        ]);
    }
}
