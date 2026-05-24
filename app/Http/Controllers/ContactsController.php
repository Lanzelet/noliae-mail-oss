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
        $accId = $ctx['account']?->id;
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 1) {
            return response()->json(['items' => []]);
        }
        // Resync passive — bon marché et garantit que les nouveaux comptes
        // mail apparaissent immédiatement dans l'autocomplete.
        $this->syncMembers($ctx['org']->id);

        $like = '%' . str_replace('%', '\\%', $q) . '%';
        $items = [];

        // 1. CARNET PERSONNEL — priorité absolue, trié par fréquence d'envoi
        if ($accId) {
            $personal = DB::table('personal_contacts')
                ->where('mail_account_id', $accId)
                ->where(function ($w) use ($like) {
                    $w->whereRaw('LOWER(email) LIKE LOWER(?)', [$like])
                      ->orWhereRaw('LOWER(COALESCE(display_name, \'\')) LIKE LOWER(?)', [$like])
                      ->orWhereRaw('LOWER(COALESCE(company, \'\')) LIKE LOWER(?)', [$like]);
                })
                ->orderByDesc('hit_count')
                ->orderByDesc('last_sent_at')
                ->orderBy('email')
                ->limit(10)
                ->get(['email', 'display_name', 'company', 'source']);
            foreach ($personal as $c) {
                $items[strtolower($c->email)] = [
                    'email'        => $c->email,
                    'display_name' => $c->display_name,
                    'company'      => $c->company,
                    'source'       => 'personal',  // tag distinct pour la popup
                    'avatar_hash'  => md5(strtolower($c->email)),
                ];
            }
        }

        // 2. CARNET ORGANISATION — complète, dédupliqué sur l'email
        $remaining = max(0, 10 - count($items));
        if ($remaining > 0) {
            $org = DB::table('organization_contacts')
                ->where('organization_id', $ctx['org']->id)
                ->where(function ($w) use ($like) {
                    $w->whereRaw('LOWER(email) LIKE LOWER(?)', [$like])
                      ->orWhereRaw('LOWER(COALESCE(display_name, \'\')) LIKE LOWER(?)', [$like])
                      ->orWhereRaw('LOWER(COALESCE(company, \'\')) LIKE LOWER(?)', [$like]);
                })
                ->orderByRaw("CASE source WHEN 'member' THEN 0 WHEN 'manual' THEN 1 ELSE 2 END")
                ->orderBy('display_name')
                ->orderBy('email')
                ->limit($remaining * 2) // pour avoir de quoi après dédup
                ->get(['email', 'display_name', 'company', 'source']);
            foreach ($org as $c) {
                $k = strtolower($c->email);
                if (isset($items[$k])) continue;
                $items[$k] = [
                    'email'        => $c->email,
                    'display_name' => $c->display_name,
                    'company'      => $c->company,
                    'source'       => $c->source,
                    'avatar_hash'  => md5(strtolower($c->email)),
                ];
                if (count($items) >= 10) break;
            }
        }
        return response()->json(['items' => array_values($items)]);
    }

    public function index(Request $request)
    {
        $ctx = $this->orgOrFail($request);
        $accId = $ctx['account']?->id;
        // Resync member contacts à la volée (fast — peu de membres)
        $this->syncMembers($ctx['org']->id);

        $q = trim((string) $request->query('q', ''));
        $like = $q !== '' ? '%' . str_replace('%', '\\%', $q) . '%' : null;

        $mapRow = function ($c) {
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
        };

        // ─── Carnet personnel
        $personal = [];
        if ($accId) {
            $q1 = DB::table('personal_contacts')->where('mail_account_id', $accId);
            if ($like) $q1->where(function ($w) use ($like) {
                $w->whereRaw('LOWER(email) LIKE LOWER(?)', [$like])
                  ->orWhereRaw('LOWER(COALESCE(display_name, \'\')) LIKE LOWER(?)', [$like])
                  ->orWhereRaw('LOWER(COALESCE(company, \'\')) LIKE LOWER(?)', [$like]);
            });
            $personal = $q1->orderByDesc('hit_count')
                ->orderByDesc('last_sent_at')
                ->orderBy('email')
                ->limit(500)->get()->map($mapRow)->values()->toArray();
        }

        // ─── Carnet organisation
        $q2 = DB::table('organization_contacts')->where('organization_id', $ctx['org']->id);
        if ($like) $q2->where(function ($w) use ($like) {
            $w->whereRaw('LOWER(email) LIKE LOWER(?)', [$like])
              ->orWhereRaw('LOWER(COALESCE(display_name, \'\')) LIKE LOWER(?)', [$like])
              ->orWhereRaw('LOWER(COALESCE(company, \'\')) LIKE LOWER(?)', [$like]);
        });
        $orgContacts = $q2->orderBy('display_name')->orderBy('email')
            ->limit(500)->get()->map($mapRow)->values()->toArray();

        return Inertia::render('Contacts', [
            'personal' => $personal,
            'org'      => $orgContacts,
            'q'        => $q,
            'role'     => $ctx['role'],
            'can_edit' => in_array($ctx['role'], ['owner', 'admin'], true),
        ]);
    }

    /* ─── CRUD carnet personnel ─── */
    public function storePersonal(Request $request)
    {
        $ctx = $this->orgOrFail($request);
        $accId = $ctx['account']?->id;
        abort_unless($accId, 401);
        $data = $request->validate([
            'email'        => 'required|email|max:320',
            'display_name' => 'nullable|string|max:120',
            'company'      => 'nullable|string|max:120',
            'job_title'    => 'nullable|string|max:120',
            'phone'        => 'nullable|string|max:32',
            'notes'        => 'nullable|string|max:2000',
        ]);
        $exists = DB::table('personal_contacts')
            ->where('mail_account_id', $accId)
            ->whereRaw('LOWER(email) = ?', [strtolower($data['email'])])->exists();
        if ($exists) {
            return back()->withErrors(['email' => 'Déjà dans ton carnet personnel.']);
        }
        DB::table('personal_contacts')->insert(array_merge($data, [
            'email'           => strtolower($data['email']),
            'mail_account_id' => $accId,
            'source'          => 'manual',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]));
        return back()->with('success', 'Contact ajouté à ton carnet personnel.');
    }

    public function updatePersonal(Request $request, int $id)
    {
        $ctx = $this->orgOrFail($request);
        $c = DB::table('personal_contacts')->where('id', $id)
            ->where('mail_account_id', $ctx['account']->id)->first();
        abort_unless($c, 404);
        $data = $request->validate([
            'display_name' => 'nullable|string|max:120',
            'company'      => 'nullable|string|max:120',
            'job_title'    => 'nullable|string|max:120',
            'phone'        => 'nullable|string|max:32',
            'notes'        => 'nullable|string|max:2000',
        ]);
        DB::table('personal_contacts')->where('id', $id)->update(
            array_merge($data, ['updated_at' => now()])
        );
        return back()->with('success', 'Contact personnel mis à jour.');
    }

    public function destroyPersonal(Request $request, int $id)
    {
        $ctx = $this->orgOrFail($request);
        DB::table('personal_contacts')->where('id', $id)
            ->where('mail_account_id', $ctx['account']->id)->delete();
        return back()->with('success', 'Contact personnel supprimé.');
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
