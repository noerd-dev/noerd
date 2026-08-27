<?php

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
        if (! TenantHelper::getSelectedTenantId()) {
            $firstTenantId = $user->tenants->first()?->id;

            if (! $firstTenantId) {
                return redirect()->route('no-tenant');
            }

            TenantHelper::setSelectedTenantId($firstTenantId);
        }

        TenantHelper::setSelectedApp('SETUP');

        if (! $user->isAdmin()) {
            abort(401);
        }

        return $next($request);
    }
}
