<?php

namespace Noerd\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Noerd\Helpers\NoerdAuth;
use Symfony\Component\HttpFoundation\Response;

class SetUserLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        // This middleware runs in the global 'web' group, so it must resolve
        // the noerd guard explicitly — never a host guard's user.
        $user = NoerdAuth::user();

        if ($user && $user->locale) {
            App::setLocale($user->locale);
        }

        return $next($request);
    }
}
