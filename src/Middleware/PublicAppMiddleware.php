<?php

namespace Noerd\Middleware;

use Closure;
use Illuminate\Http\Request;
use Noerd\Exceptions\NoerdException;
use Noerd\Helpers\AccessHelper;
use Noerd\Helpers\NoerdAuth;
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
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $appName): Response
    {
        $publicApp = TenantApp::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($appName)])
            ->where('is_active', true)
            ->where('is_public', true)
            ->first();

        if ($publicApp) {
            // Set selected_app for public (guest) access if not already set
            if (!TenantHelper::getSelectedApp()) {
                TenantHelper::setSelectedApp(mb_strtoupper($appName));
            }

            $this->resolveGuestTenant($publicApp);

            return $next($request);
        }

        $user = NoerdAuth::user();

        if (!$user) {
            return redirect()->route('noerd.login');
        }

        $tenant = TenantHelper::getSelectedTenant();

        if (!$tenant) {
            return redirect('/');
        }

        $hasApp = $tenant->tenantApps()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($appName)])
            ->exists();

        if (!$hasApp) {
            throw new NoerdException(
                NoerdException::TYPE_APP_NOT_ASSIGNED,
                appName: mb_strtoupper($appName),
            );
        }

        if (!AccessHelper::canAccessApp($appName)) {
            throw new NoerdException(
                NoerdException::TYPE_APP_ACCESS_DENIED,
                appName: mb_strtoupper($appName),
            );
        }

        if (!TenantHelper::getSelectedApp()) {
            TenantHelper::setSelectedApp(mb_strtoupper($appName));
        }

        return $next($request);
    }

    /**
     * Establish the tenant a guest browses a public app in. Without it the tenant
     * scope has nothing to filter by and would have to fall back to showing every
     * tenant's rows.
     *
     * Only an unambiguous assignment can be resolved here: a public app shared by
     * SEVERAL tenants must establish the context itself (e.g. resolved from the
     * request host) via TenantHelper::setGuestTenantId() — until it does, guest
     * queries on tenant-owned models yield no rows instead of foreign data.
     */
    private function resolveGuestTenant(TenantApp $publicApp): void
    {
        if (NoerdAuth::check()) {
            return;
        }

        TenantHelper::markPublicAppGuest();

        if (TenantHelper::getGuestTenantId() !== null) {
            return;
        }

        $tenantIds = $publicApp->tenants()->pluck('tenants.id');

        if ($tenantIds->count() === 1) {
            TenantHelper::setGuestTenantId((int) $tenantIds->first());
        }
    }
}
