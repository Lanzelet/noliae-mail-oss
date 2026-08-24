<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Ajoute Cache-Control: no-transform pour empêcher un proxy intermédiaire
 * (Cloudflare) de recompresser la réponse (ex. en zstd) — certains clients
 * décodent mal ce ré-encodage sur les requêtes XHR d'Inertia (la navigation
 * complète du navigateur, elle, décode toujours correctement).
 */
class NoTransformResponse
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $cc = (string) $response->headers->get('Cache-Control', '');
        if (! str_contains($cc, 'no-transform')) {
            $response->headers->set('Cache-Control', trim($cc !== '' ? $cc . ', no-transform' : 'no-transform', ', '));
        }
        return $response;
    }
}
