<?php

namespace Noerd\Middleware;

use Closure;
use Illuminate\Http\Request;
use Noerd\Exceptions\NoerdException;
use Noerd\Helpers\TenantHelper;
use Symfony\Component\HttpFoundation\Response;

class AppAccessMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $appName): Response
    {
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

        // Only set selected_app if none is currently selected
        if (! TenantHelper::getSelectedApp()) {
            TenantHelper::setSelectedApp(mb_strtoupper($appName));
        }

        return $next($request);
    }
}
