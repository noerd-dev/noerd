<?php

declare(strict_types=1);

namespace Noerd\Middleware;

use Closure;
use Illuminate\Http\Request;
use Noerd\Enums\NoerdExceptionType;
use Noerd\Exceptions\NoerdException;
use Noerd\Helpers\AccessHelper;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the routes of a tenant app (`app-access:crm` or `app-access:crm,sales`
 * for routes shared by several apps): the tenant must run one of the apps and
 * the user must be allowed to access it. The matching app becomes the selected
 * app, so the navigation always shows the app the route belongs to.
 */
class AppAccessMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$appNames): Response
    {
        $user = NoerdAuth::user();

        if (! $user) {
            return redirect()->route('noerd.login');
        }

        $tenant = TenantHelper::getSelectedTenant();

        if (! $tenant) {
            return redirect()->route('noerd.no-tenant');
        }

        $appNames = array_values(array_filter(array_map(
            fn(string $name): string => mb_strtolower(mb_trim($name)),
            $appNames,
        )));

        // In single-tenant mode every app is assigned — only the per-app
        // authorization gate (see AccessHelper) applies.
        $assigned = config('noerd.features.multi_tenant')
            ? $tenant->tenantApps()->namedAny($appNames)->pluck('name')->map(fn(string $name): string => mb_strtolower($name))->all()
            : $appNames;

        // The first candidate in route order wins.
        $matchingApp = null;
        foreach ($appNames as $candidate) {
            if (in_array($candidate, $assigned, true)) {
                $matchingApp = $candidate;
                break;
            }
        }

        if (! $matchingApp) {
            throw new NoerdException(
                NoerdExceptionType::AppNotAssigned,
                appName: mb_strtoupper($appNames[0] ?? ''),
            );
        }

        if (! AccessHelper::canAccessApp($matchingApp)) {
            throw new NoerdException(
                NoerdExceptionType::AppAccessDenied,
                appName: mb_strtoupper($matchingApp),
            );
        }

        if (TenantHelper::getSelectedApp() !== mb_strtoupper($matchingApp)) {
            TenantHelper::setSelectedApp(mb_strtoupper($matchingApp));
        }

        return $next($request);
    }
}
