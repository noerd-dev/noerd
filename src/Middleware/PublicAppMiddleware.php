<?php

namespace Noerd\Middleware;

use Closure;
use Illuminate\Http\Request;
use Noerd\Exceptions\NoerdException;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\TenantApp;
use Symfony\Component\HttpFoundation\Response;

class PublicAppMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Check if the app is public and active. If so, allow access without authentication.
     * Otherwise, fall back to normal authentication and tenant-based access control.
     *
     * @param  Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $appName): Response
    {
        $isPublicApp = TenantApp::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($appName)])
            ->where('is_active', true)
            ->where('is_public', true)
            ->exists();

        if ($isPublicApp) {
            // Set selected_app for public (guest) access if not already set
            if (! TenantHelper::getSelectedApp()) {
                TenantHelper::setSelectedApp(mb_strtoupper($appName));
            }

            return $next($request);
        }

        $user = auth()->user();

        if (! $user) {
            return redirect('/login');
        }

        $tenant = TenantHelper::getSelectedTenant();

        if (! $tenant) {
            return redirect('/');
        }

        $hasApp = $tenant->tenantApps()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($appName)])
            ->exists();

        if (! $hasApp) {
            throw new NoerdException(
                NoerdException::TYPE_APP_NOT_ASSIGNED,
                appName: mb_strtoupper($appName),
            );
        }

        if (! TenantHelper::getSelectedApp()) {
            TenantHelper::setSelectedApp(mb_strtoupper($appName));
        }

        return $next($request);
    }
}
