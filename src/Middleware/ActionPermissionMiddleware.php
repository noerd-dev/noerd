<?php

declare(strict_types=1);

namespace Noerd\Middleware;

use Closure;
use Illuminate\Http\Request;
use Noerd\Helpers\AccessHelper;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards a route behind a named action permission, e.g.
 * `->middleware('action-permission:production.start-run')`. The decision is
 * AccessHelper::canPerformAction() — an undefined action gate falls back to
 * the profile baseline (see AccessHelper).
 */
class ActionPermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $actionKey): Response
    {
        abort_unless(
            AccessHelper::canPerformAction($actionKey),
            403,
            __('You are not allowed to perform this action.'),
        );

        return $next($request);
    }
}
