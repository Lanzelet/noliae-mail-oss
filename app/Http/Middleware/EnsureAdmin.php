<?php

namespace App\Http\Middleware;

use App\Services\AppSettings;
use App\Services\OrgContext;
use Closure;
use Illuminate\Http\Request;

/**
 * Defense-in-depth pour /admin/* : exige session webmail + rôle owner/admin/support
 * dans l'organisation OU email == legacy admin_email (rétrocompat).
 *
 * Le contrôleur AdminController appelle aussi authorize_admin() au cas où.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = strtolower((string) $request->session()->get('mail_user', ''));
        if (! $user) {
            return redirect('/');
        }

        $ctx = OrgContext::current($request);
        $role = $ctx['role'] ?? 'guest';

        // Rétrocompat : si l'email correspond à app_settings.admin_email,
        // on l'autorise même sans organization_members (utile au first-boot
        // d'une instance pas encore migrée vers Orgs).
        $legacyAdmin = strtolower((string) AppSettings::get('admin_email', env('ADMIN_EMAIL', '')));
        if (! OrgContext::isAdmin($role) && $user !== $legacyAdmin) {
            abort(403, 'Accès admin réservé. (Rôle requis : owner / admin / support)');
        }
        // Stocke contexte pour les controllers
        $request->attributes->set('org_ctx', $ctx);
        return $next($request);
    }
}
