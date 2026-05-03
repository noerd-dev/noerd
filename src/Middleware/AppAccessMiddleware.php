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
    public function handle(Request $request, Closure $next, string ...$appNames): Response
    {
        $appName = implode(',', $appNames);
        $user = auth()->user();

        if (! $user) {
            return redirect('/login');
        }

        $tenant = TenantHelper::getSelectedTenant();

        if (! $tenant) {
            return redirect('/');
        }

        // In single-tenant mode, all apps are always accessible
        if (! config('noerd.features.multi_tenant')) {
            TenantHelper::setSelectedAppFromRoute();

            return $next($request);
        }

        $appNames = array_map(
            fn(string $name): string => mb_strtolower(mb_trim($name)),
            explode(',', $appName),
        );

        $matchingApp = null;
        foreach ($appNames as $candidate) {
            $found = $tenant->tenantApps()
                ->whereRaw('LOWER(name) = ?', [$candidate])
                ->value('name');
            if ($found) {
                $matchingApp = $found;
                break;
            }
        }

        if (! $matchingApp) {
            throw new NoerdException(
                NoerdException::TYPE_APP_NOT_ASSIGNED,
                appName: mb_strtoupper($appNames[0]),
            );
        }

        // Only set selected_app if none is currently selected
        if (! TenantHelper::getSelectedApp()) {
            TenantHelper::setSelectedApp(mb_strtoupper($matchingApp));
        }

        return $next($request);
    }
}
