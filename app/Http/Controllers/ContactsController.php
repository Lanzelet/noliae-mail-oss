<?php

namespace App\Http\Controllers;

use App\Services\OrgContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Carnet d'adresses partagé de l'organisation.
 *
 * - source=member : ligne dérivée d'un mail_accounts de l'orga (read-only en UI,
 *   resynchronisée par syncMembers())
 * - source=manual : créée par l'admin/owner (CRUD complet)
 * - source=imported : import CSV (futur)
 *
 * Tout membre lit. Seuls owner/admin peuvent créer/éditer/supprimer.
 */
class ContactsController extends Controller
{
    private function orgOrFail(Request $request)
    {
        $ctx = OrgContext::current($request);
        abort_unless($ctx['org'], 404);
        return $ctx;
    }

    /**
     * GET /api/contacts/suggest?q=foo
     *
     * Autocomplete pour compose webmail (style O365). Cherche dans le carnet
     * d'adresses de l'orga (membres + manuels) par prefix sur display_name OU
     * email. Retourne max 10 résultats triés (membres d'abord, puis manuels,
     * puis alpha). Format JSON minimal pour rester rapide.
     */
    public function suggest(Request $request)
    {
        $ctx = $this->orgOrFail($request);
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 1) {
            return response()->json(['items' => []]);
        }
        // Resync passive — bon marché et garantit que les nouveaux comptes
        // mail apparaissent immédiatement dans l'autocomplete.
        $this->syncMembers($ctx['org']->id);

        $like = '%' . str_replace('%', '\\%', $q) . '%';
        $items = DB::table('organization_contacts')
            ->where('organization_id', $ctx['org']->id)
            ->where(function ($w) use ($like) {
                $w->whereRaw('LOWER(email) LIKE LOWER(?)', [$like])
                  ->orWhereRaw('LOWER(COALESCE(display_name, \'\')) LIKE LOWER(?)', [$like])
                  ->orWhereRaw('LOWER(COALESCE(company, \'\')) LIKE LOWER(?)', [$like]);
            })
            ->orderByRaw("CASE source WHEN 'member' THEN 0 WHEN 'manual' THEN 1 ELSE 2 END")
            ->orderBy('display_name')
            ->orderBy('email')
            ->limit(10)
            ->get(['email', 'display_name', 'company', 'source'])
            ->map(function ($c) {
                return [
                    'email'        => $c->email,
                    'display_name' => $c->display_name,
                    'company'      => $c->company,
                    'source'       => $c->source,
                    'avatar_hash'  => md5(strtolower($c->email)),
                ];
            })->values();
        return response()->json(['items' => $items]);
    }

    public function index(Request $request)
    {
        $ctx = $this->orgOrFail($request);
        // Resync member contacts à la volée (fast — peu de membres)
        $this->syncMembers($ctx['org']->id);

        $q = trim((string) $request->query('q', ''));
        $query = DB::table('organization_contacts')
            ->where('organization_id', $ctx['org']->id);
        if ($q !== '') {
            $like = '%' . str_replace('%', '\\%', $q) . '%';
            $query->where(function ($w) use ($like) {
                $w->whereRaw('LOWER(email) LIKE LOWER(?)', [$like])
                  ->orWhereRaw('LOWER(COALESCE(display_name, \'\')) LIKE LOWER(?)', [$like])
                  ->orWhereRaw('LOWER(COALESCE(company, \'\')) LIKE LOWER(?)', [$like]);
            });
        }
        $contacts = $query->orderBy('display_name')->orderBy('email')
            ->limit(500)->get()->map(function ($c) {
                return [
                    'id'           => $c->id,
                    'email'        => $c->email,
                    'display_name' => $c->display_name,
                    'company'      => $c->company,
                    'job_title'    => $c->job_title,
                    'phone'        => $c->phone,
                    'notes'        => $c->notes,
                    'source'       => $c->source,
                    'avatar_hash'  => md5(strtolower($c->email)),
                ];
            })->values()->toArray();
        return Inertia::render('Contacts', [
            'contacts' => $contacts,
            'q'        => $q,
            'role'     => $ctx['role'],
            'can_edit' => in_array($ctx['role'], ['owner', 'admin'], true),
        ]);
    }

    public function store(Request $request)
    {
        $ctx = $this->orgOrFail($request);
        abort_unless(in_array($ctx['role'], ['owner', 'admin'], true), 403);
        $data = $request->validate([
            'email'        => 'required|email|max:320',
            'display_name' => 'nullable|string|max:120',
            'company'      => 'nullable|string|max:120',
            'job_title'    => 'nullable|string|max:120',
            'phone'        => 'nullable|string|max:32',
            'notes'        => 'nullable|string|max:2000',
        ]);
        $exists = DB::table('organization_contacts')
            ->where('organization_id', $ctx['org']->id)
            ->whereRaw('LOWER(email) = ?', [strtolower($data['email'])])->exists();
        if ($exists) {
            return back()->withErrors(['email' => 'Contact déjà présent dans le carnet.']);
        }
        DB::table('organization_contacts')->insert(array_merge($data, [
            'email'           => strtolower($data['email']),
            'organization_id' => $ctx['org']->id,
            'source'          => 'manual',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]));
        return back()->with('success', 'Contact ajouté.');
    }

    public function update(Request $request, int $id)
    {
        $ctx = $this->orgOrFail($request);
        abort_unless(in_array($ctx['role'], ['owner', 'admin'], true), 403);
        $c = DB::table('organization_contacts')->where('id', $id)
            ->where('organization_id', $ctx['org']->id)->first();
        abort_unless($c, 404);
        if ($c->source === 'member') {
            return back()->withErrors(['source' => 'Ce contact est dérivé d\'un membre — édite via /admin/accounts.']);
        }
        $data = $request->validate([
            'display_name' => 'nullable|string|max:120',
            'company'      => 'nullable|string|max:120',
            'job_title'    => 'nullable|string|max:120',
            'phone'        => 'nullable|string|max:32',
            'notes'        => 'nullable|string|max:2000',
        ]);
        DB::table('organization_contacts')->where('id', $id)->update(
            array_merge($data, ['updated_at' => now()])
        );
        return back()->with('success', 'Contact mis à jour.');
    }

    public function destroy(Request $request, int $id)
    {
        $ctx = $this->orgOrFail($request);
        abort_unless(in_array($ctx['role'], ['owner', 'admin'], true), 403);
        $c = DB::table('organization_contacts')->where('id', $id)
            ->where('organization_id', $ctx['org']->id)->first();
        abort_unless($c, 404);
        if ($c->source === 'member') {
            return back()->withErrors(['source' => 'Ce contact est dérivé d\'un membre — désactive le compte dans /admin/accounts.']);
        }
        DB::table('organization_contacts')->where('id', $id)->delete();
        return back()->with('success', 'Contact supprimé.');
    }

    /**
     * Resync : tous les mail_accounts actifs des domaines de l'orga apparaissent
     * dans organization_contacts avec source=member. Idempotent.
     */
    private function syncMembers(int $orgId): void
    {
        $domainIds = DB::table('mail_domains')->where('organization_id', $orgId)->pluck('id');
        if ($domainIds->isEmpty()) return;
        $accounts = DB::table('mail_accounts')
            ->whereIn('domain_id', $domainIds)
            ->where('active', true)
            ->get(['email', 'display_name']);
        foreach ($accounts as $a) {
            DB::table('organization_contacts')->updateOrInsert(
                ['organization_id' => $orgId, 'email' => strtolower($a->email)],
                [
                    'display_name' => $a->display_name,
                    'source'       => 'member',
                    'updated_at'   => now(),
                    'created_at'   => DB::raw("COALESCE((SELECT created_at FROM organization_contacts WHERE organization_id = $orgId AND LOWER(email) = LOWER('" . str_replace("'", "''", $a->email) . "') LIMIT 1), NOW())"),
                ]
            );
        }
        // Cleanup : retire les member-rows orphelines
        $emails = $accounts->pluck('email')->map(fn ($e) => strtolower($e));
        DB::table('organization_contacts')
            ->where('organization_id', $orgId)
            ->where('source', 'member')
            ->whereNotIn(DB::raw('LOWER(email)'), $emails)
            ->delete();
    }
}
