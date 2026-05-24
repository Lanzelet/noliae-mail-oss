<?php

namespace App\Http\Middleware;

use App\Services\AppSettings;
use Closure;
use Illuminate\Http\Request;

/**
 * Defense-in-depth pour /admin/* : exige session webmail + email == admin_email.
 *
 * Le contrôleur AdminController appelle déjà authorize_admin() dans chaque
 * méthode. Ce middleware double-check au cas où une nouvelle méthode
 * oublierait l'appel — l'attaquant n'atteint jamais le contrôleur.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = strtolower((string) $request->session()->get('mail_user', ''));
        if (! $user) {
            return redirect('/');
        }
        $admin = strtolower((string) AppSettings::get('admin_email', env('ADMIN_EMAIL', '')));
        if (! $admin || $user !== $admin) {
            abort(403, 'Accès admin réservé.');
        }
        return $next($request);
    }
}
