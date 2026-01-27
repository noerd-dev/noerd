<?php

namespace Noerd\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $user = auth()->user();
        if (! TenantHelper::getSelectedTenantId()) {
            $firstTenantId = $user->tenants->first()?->id;

            if (! $firstTenantId) {
                return redirect('no-tenant');
            }

            TenantHelper::setSelectedTenantId($firstTenantId);
        }

        TenantHelper::setSelectedApp('SETUP');

        if (! Auth::user()->isAdmin()) {
            abort(401);
        }

        return $next($request);
    }
}
