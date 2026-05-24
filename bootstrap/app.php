<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Derrière Cloudflare + Traefik : on fait confiance au proxy (HTTPS).
        $middleware->trustProxies(at: '*');
        $middleware->validateCsrfTokens(except: [
            'api/v1/mail/*',
            'webmail/img',      // proxy d'image HMAC-signé, pas de session
            'webmail/avatar/*', // SVG/PNG avatars publics
        ]);
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);
        $middleware->alias([
            'org.role'  => \App\Http\Middleware\EnsureOrgRole::class,
            'force.2fa' => \App\Http\Middleware\Force2faForAdmins::class,
            'admin'     => \App\Http\Middleware\EnsureAdmin::class,
            'mailbox'   => \App\Http\Middleware\EnsureMailbox::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
