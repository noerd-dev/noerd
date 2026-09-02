<?php

declare(strict_types=1);

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
        // This middleware runs in the global 'web' group DELIBERATELY: Livewire's
        // update endpoint runs on 'web', and component updates render translated
        // strings — scoping the locale to the noerd groups would drop it on every
        // Livewire request. It must resolve the noerd guard explicitly (never a
        // host guard's user), and the locale read is write-free (see
        // NoerdUser::getLocaleAttribute), so a host route pays at most one read.
        $user = NoerdAuth::user();

        if ($user && $user->locale) {
            App::setLocale($user->locale);
        }

        return $next($request);
    }
}
