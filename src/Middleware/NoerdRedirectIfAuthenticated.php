<?php

declare(strict_types=1);

namespace Noerd\Middleware;

use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Http\Request;

/**
 * Guest middleware for the noerd-guest route group. An already authenticated
 * noerd user is sent to the apps dashboard — never to the framework default
 * (route('dashboard') / route('home')) or a host-registered redirectUsing()
 * callback, both of which may belong to a coexisting starter kit.
 */
class NoerdRedirectIfAuthenticated extends RedirectIfAuthenticated
{
    protected function redirectTo(Request $request): ?string
    {
        return route('noerd.apps');
    }
}
