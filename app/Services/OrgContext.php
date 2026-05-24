<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Noliae Mail OSS — résout l'organisation courante à partir du compte connecté.
 *
 * MVP : 1 user appartient à exactement 1 organisation (sa colonne domain →
 * mail_domains.organization_id). Si plus tard on veut multi-orga par user, on
 * remplace cette logique sans toucher les controllers.
 */
class OrgContext
{
    private const ROLE_RANK = ['member' => 0, 'support' => 1, 'admin' => 2, 'owner' => 3];

    /** Retourne ['org' => obj|null, 'role' => string, 'account' => obj|null]. */
    public static function current(Request $request): array
    {
        $email = strtolower((string) $request->session()->get('mail_user', ''));
        if (! $email) {
            return ['org' => null, 'role' => 'guest', 'account' => null];
        }
        $account = DB::table('mail_accounts')->where('email', $email)->first();
        if (! $account) {
            return ['org' => null, 'role' => 'guest', 'account' => null];
        }
        $orgId = DB::table('mail_domains')->where('id', $account->domain_id)->value('organization_id');
        if (! $orgId) {
            return ['org' => null, 'role' => 'member', 'account' => $account];
        }
        $org = DB::table('organizations')->where('id', $orgId)->first();
        $role = DB::table('organization_members')
            ->where('organization_id', $orgId)
            ->where('mail_account_id', $account->id)
            ->value('role') ?: 'member';

        return ['org' => $org, 'role' => $role, 'account' => $account];
    }

    /** Vérifie qu'un user a au moins le rôle attendu (member < support < admin < owner). */
    public static function hasRole(string $userRole, string $required): bool
    {
        return (self::ROLE_RANK[$userRole] ?? -1) >= (self::ROLE_RANK[$required] ?? 99);
    }

    /** Liste des rôles requis ayant accès au panneau admin. */
    public static function adminRoles(): array
    {
        return ['owner', 'admin', 'support'];
    }

    /** Le user passé peut-il accéder à /admin ? */
    public static function isAdmin(string $role): bool
    {
        return in_array($role, self::adminRoles(), true);
    }
}
