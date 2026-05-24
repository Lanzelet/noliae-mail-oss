<?php

namespace App\Http\Middleware;

use App\Services\OrgContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 2FA obligatoire pour tous les rôles owner / admin / support : si l'utilisateur
 * n'a pas activé TOTP, on le redirige vers /account avec un message bloquant.
 *
 * À appliquer APRES EnsureAdmin dans le middleware stack.
 */
class Force2faForAdmins
{
    public function handle(Request $request, Closure $next)
    {
        $email = strtolower((string) $request->session()->get('mail_user', ''));
        if (! $email) {
            return $next($request);
        }
        $account = DB::table('mail_accounts')->where('email', $email)->first();
        if (! $account || $account->totp_enabled) {
            return $next($request);
        }
        $role = OrgContext::current($request)['role'] ?? 'member';
        if (! OrgContext::isAdmin($role)) {
            return $next($request);
        }
        // Bypass quand le user est DÉJÀ sur /account (pour pouvoir activer la 2FA)
        if (str_starts_with($request->path(), 'account')) {
            return $next($request);
        }
        return redirect('/account?force_2fa=1')->with(
            'error',
            '2FA obligatoire pour les administrateurs. Active TOTP ci-dessous pour accéder à l\'admin.'
        );
    }
}
