<?php

namespace Noerd\Middleware;

use Closure;
use Illuminate\Http\Request;
use Noerd\Exceptions\NoerdException;
use Noerd\Helpers\AccessHelper;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Symfony\Component\HttpFoundation\Response;

class AppAccessMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$appNames): Response
    {
        $appName = implode(',', $appNames);
        $user = NoerdAuth::user();

        if (!$user) {
            return redirect()->route('noerd.login');
        }

        $tenant = TenantHelper::getSelectedTenant();

        if (!$tenant) {
            return redirect('/');
        }

        // In single-tenant mode, every app is assigned — but the per-app
        // authorization gate (see AccessHelper) still applies.
        if (!config('noerd.features.multi_tenant')) {
            $accessible = array_filter(
                $appNames,
                fn(string $candidate): bool => AccessHelper::canAccessApp(mb_trim($candidate)),
            );
            if ($accessible === []) {
                throw new NoerdException(
                    NoerdException::TYPE_APP_ACCESS_DENIED,
                    appName: mb_strtoupper(mb_trim($appNames[0])),
                );
            }

            TenantHelper::setSelectedAppFromRoute();

            return $next($request);
        }

        $appNames = array_map(
            fn(string $name): string => mb_strtolower(mb_trim($name)),
            explode(',', $appName),
        );

        // ONE query for all candidates; the first candidate in route order wins,
        // matching the old per-candidate loop (which cost one query each).
        $assignedByLower = $tenant->tenantApps()
            ->namedAny($appNames)
            ->pluck('name')
            ->keyBy(fn(string $name): string => mb_strtolower($name));

        $matchingApp = null;
        foreach ($appNames as $candidate) {
            if ($assignedByLower->has($candidate)) {
                $matchingApp = $assignedByLower[$candidate];
                break;
            }
        }

        if (!$matchingApp) {
            throw new NoerdException(
                NoerdException::TYPE_APP_NOT_ASSIGNED,
                appName: mb_strtoupper($appNames[0]),
            );
        }

        if (!AccessHelper::canAccessApp($matchingApp)) {
            throw new NoerdException(
                NoerdException::TYPE_APP_ACCESS_DENIED,
                appName: mb_strtoupper($matchingApp),
            );
        }

        // Only select the app when the session carries no selection yet
        if (!TenantHelper::getSelectedApp()) {
            TenantHelper::setSelectedApp(mb_strtoupper($matchingApp));
        }

        return $next($request);
    }
}
