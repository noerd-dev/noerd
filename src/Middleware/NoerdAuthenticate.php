<?php

declare(strict_types=1);

namespace Noerd\Middleware;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;

/**
 * Authenticate middleware for the noerd route groups. Guests are always sent
 * to noerd's own login route — never to the host's route('login'), which may
 * belong to a coexisting starter kit and authenticates against another guard.
 */
class NoerdAuthenticate extends Authenticate
{
    protected function redirectTo(Request $request): ?string
    {
        return route('noerd.login');
    }
}
