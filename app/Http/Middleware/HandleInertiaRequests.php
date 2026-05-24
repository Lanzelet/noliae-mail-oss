<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'mailbox' => fn () => $request->session()->get('mail_user'),
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
