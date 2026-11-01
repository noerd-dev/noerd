<?php

declare(strict_types=1);

namespace Noerd\Middleware;

use Closure;
use Illuminate\Http\Request;
use Noerd\Helpers\TenantHelper;
use Symfony\Component\HttpFoundation\Response;

/**
 * The profile area is a navigation context like the setup: its routes select
 * the hidden `PROFILE` app, so the sidebar shows `app-configs/profile/navigation.yml`
 * — the core's profile pages plus every entry a module added with
 * ensureProfileNavigation(). Unlike setup it is open to every signed-in person
 * (the route group's `noerd` middleware authenticates).
 */
class ProfileMiddleware
{
    public const string APP = 'PROFILE';

    public function handle(Request $request, Closure $next): Response
    {
        TenantHelper::setSelectedApp(self::APP);

        return $next($request);
    }
}
