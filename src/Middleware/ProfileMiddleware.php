<?php

declare(strict_types=1);

namespace Noerd\Middleware;

use Closure;
use Illuminate\Http\Request;
use Noerd\Helpers\TenantHelper;
use Noerd\Support\Impersonation;
use Symfony\Component\HttpFoundation\Response;

/**
 * The profile area is a navigation context like the setup: its routes select
 * the hidden `PROFILE` app, so the sidebar shows `app-configs/profile/navigation.yml`
 * — the core's profile pages plus every entry a module added with
 * ensureProfileNavigation(). Unlike setup it is open to every signed-in person
 * (the route group's `noerd` middleware authenticates).
 *
 * The profile holds the person's OWN account — name, e-mail, password,
 * connected assistants, the account itself. An administrator signed in as the
 * person ("Login as user") never reaches it: impersonation is for seeing the
 * apps as the person does, not for taking over their account. Registered as
 * persistent Livewire middleware, so the component updates of a profile page
 * are refused as well.
 */
class ProfileMiddleware
{
    public const string APP = 'PROFILE';

    public function handle(Request $request, Closure $next): Response
    {
        abort_if(Impersonation::active(), 403);

        TenantHelper::setSelectedApp(self::APP);

        return $next($request);
    }
}
