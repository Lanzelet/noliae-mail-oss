<?php

namespace App\Http\Middleware;

use App\Services\OrgContext;
use Closure;
use Illuminate\Http\Request;

/**
 * Usage : ->middleware('org.role:owner')   ou   ->middleware('org.role:admin,owner')
 *
 * Vérifie que l'utilisateur courant a au moins UN des rôles passés. Si la
 * liste contient un rôle hiérarchique unique (ex: 'admin'), on accepte aussi
 * les niveaux supérieurs (owner). À l'inverse, 'owner' n'accepte QUE owner.
 */
class EnsureOrgRole
{
    public function handle(Request $request, Closure $next, ...$allowed)
    {
        $ctx = OrgContext::current($request);
        $role = $ctx['role'] ?? 'guest';
        if (! $allowed) {
            abort(500, 'EnsureOrgRole sans rôle');
        }
        // Match exact OR hiérarchique
        foreach ($allowed as $r) {
            if ($role === $r || OrgContext::hasRole($role, $r)) {
                $request->attributes->set('org_ctx', $ctx);
                return $next($request);
            }
        }
        abort(403, 'Rôle requis : ' . implode(' / ', $allowed));
    }
}
