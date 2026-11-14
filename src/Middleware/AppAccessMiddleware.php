<?php

declare(strict_types=1);

namespace Noerd\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Noerd\Enums\NoerdExceptionType;
use Noerd\Exceptions\NoerdException;
use Noerd\Helpers\AccessHelper;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the routes of a tenant app (`app-access:crm` or `app-access:crm,sales`
 * for routes shared by several apps): the tenant must run one of the apps and
 * the user must be allowed to access it — the first app of the route that is
 * assigned AND accessible matches, so one usable app suffices. The matching app
 * becomes the selected app, so the navigation always shows the app the route
 * belongs to.
 *
 * A HIDDEN app (tenant_app.is_hidden) never becomes the selected app: it backs
 * the screens of another app (liefertool pulls PRODUCT in, booking-members
 * keeps BOOKING) and is refused by the app switcher, so the user stays in the
 * app they came from and keeps its navigation and its app-configs.
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

        $assignedRows = $tenant->tenantApps()->namedAny($appNames)->get();
        $assignedNames = $assignedRows->pluck('name')->map(fn(string $name): string => mb_strtolower($name))->all();

        // The route's apps the tenant runs, in ROUTE order.
        $assigned = array_values(array_filter(
            $appNames,
            fn(string $candidate): bool => in_array($candidate, $assignedNames, true),
        ));

        if ($assigned === []) {
            throw new NoerdException(
                NoerdExceptionType::AppNotAssigned,
                appName: mb_strtoupper($appNames[0] ?? ''),
            );
        }

        // Of those, the ones the user may access — the first one wins. ONE
        // accessible app suffices (the rule of AccessHelper::canUseApp()): a
        // shared route is denied only when the user may access none of its apps.
        $accessible = array_values(array_filter(
            $assigned,
            fn(string $candidate): bool => AccessHelper::canAccessApp($candidate),
        ));

        if ($accessible === []) {
            throw new NoerdException(
                NoerdExceptionType::AppAccessDenied,
                appName: mb_strtoupper($assigned[0]),
            );
        }

        $this->selectApp($tenant, $accessible[0], $accessible, $assignedRows);

        return $next($request);
    }

    /**
     * Decide which app the navigation shows for this request.
     *
     * The selected app is kept when it is one of the route's own apps, or when
     * the route belongs to a hidden app and the selected app is one the tenant
     * runs and the user may access. Otherwise the first VISIBLE assigned
     * candidate is selected — a hidden one only as the very last resort (no
     * selection yet, or one that is no longer valid for this tenant).
     *
     * @param  list<string>  $assigned  lowercase names of the route's apps the tenant runs and the user may access, in route order
     * @param  Collection<int, TenantApp>  $assignedRows  their rows with the pivot
     */
    private function selectApp(Tenant $tenant, string $matchingApp, array $assigned, Collection $assignedRows): void
    {
        $selected = TenantHelper::getSelectedApp();
        $selectedLower = $selected ? mb_strtolower($selected) : null;

        if ($selectedLower !== null && in_array($selectedLower, $assigned, true)) {
            return;
        }

        $hidden = $assignedRows
            ->filter(fn(TenantApp $app): bool => (bool) $app->pivot->getAttribute('is_hidden'))
            ->map(fn(TenantApp $app): string => mb_strtolower($app->name))
            ->values()
            ->all();

        $visibleCandidates = array_values(array_diff($assigned, $hidden));

        if ($visibleCandidates === []
            && $selectedLower !== null
            && $this->tenantRunsApp($tenant, $selectedLower)
            && AccessHelper::canAccessApp($selectedLower)) {
            return;
        }

        TenantHelper::setSelectedApp(mb_strtoupper($visibleCandidates[0] ?? $matchingApp));
    }

    private function tenantRunsApp(Tenant $tenant, string $appName): bool
    {
        return $tenant->tenantApps()->namedAny([$appName])->exists();
    }
}
