<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Exige une session webmail ouverte (sinon → page de connexion). */
class EnsureMailbox
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->session()->get('mail_user')) {
            return redirect('/');
        }
        return $next($request);
    }
}
