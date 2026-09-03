<?php

declare(strict_types=1);

namespace Noerd\Middleware;

use Closure;
use Illuminate\Http\Request;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Symfony\Component\HttpFoundation\Response;

class SetupMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = NoerdAuth::user();

        // A guest is not "forbidden", they are unauthenticated — send them to
        // the login screen exactly like AppAccessMiddleware does.
        if (! $user) {
            return redirect()->route('noerd.login');
        }

        if (! TenantHelper::getSelectedTenantId()) {
            $firstTenantId = $user->accessibleTenants()->first()?->id;

            if (! $firstTenantId) {
                return redirect()->route('noerd.no-tenant');
            }

            TenantHelper::setSelectedTenantId($firstTenantId);
        }

        // Authorization comes before the session switch: a denied user must
        // not be left with the setup app selected.
        if (! $user->isAdmin()) {
            abort(403);
        }

        TenantHelper::setSelectedApp('SETUP');

        return $next($request);
    }
}
