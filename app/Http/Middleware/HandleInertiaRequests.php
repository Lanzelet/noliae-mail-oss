<?php

namespace App\Http\Middleware;

use App\Services\OrgContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        // Contexte org partagé pour TOUS les Inertia render (sidebar admin
        // affiche org_name + user_role sans que chaque controller doive le passer).
        $org_name = null;
        $user_role = null;
        $me_email = null;
        if ($request->session()->has('mail_user')) {
            try {
                $ctx = OrgContext::current($request);
                $org_name = $ctx['org']?->name;
                $user_role = $ctx['role'] ?? null;
                $me_email = strtolower((string) $request->session()->get('mail_user'));
            } catch (\Throwable $e) {
                // Ne casse pas le rendu si la table n'existe pas (premier boot).
            }
        }
        return array_merge(parent::share($request), [
            'mailbox'   => fn () => $request->session()->get('mail_user'),
            'org_name'  => fn () => $org_name,
            'user_role' => fn () => $user_role,
            'me_email'  => fn () => $me_email,
            'flash'   => [
                'success'        => fn () => $request->session()->get('success'),
                'error'          => fn () => $request->session()->get('error'),
                'totp_setup'     => fn () => $request->session()->get('totp_setup'),
                'totp_activated' => fn () => $request->session()->get('totp_activated'),
                'new_token'      => fn () => $request->session()->get('new_token'),
            ],
        ]);
    }
}
