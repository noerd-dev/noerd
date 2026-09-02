<?php

declare(strict_types=1);

namespace Noerd\Middleware;

use Closure;
use Illuminate\Http\Request;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the session's selected tenant in sync with the user's actual
 * membership.
 *
 * The tenant is written to the session by paths that all validate membership
 * at the time of writing (login, the tenant switcher, the setup middleware),
 * but nothing re-checked it afterwards. Revoking a user's access therefore only
 * took effect at their next login: until then the tenant scope kept scoping
 * every query to the revoked tenant, so the user kept reading — and writing —
 * its data.
 *
 * On a revoked tenant the session falls back to another tenant the user still
 * belongs to, or to none at all (the setup middleware then routes them to the
 * no-tenant screen). A super admin may work in every tenant of the
 * installation, so only a DELETED tenant is ever unselected for one.
 */
class EnsureTenantMembership
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = NoerdAuth::user();
        $selectedTenantId = TenantHelper::getSelectedTenantId();

        if ($user && $selectedTenantId && ! $user->canAccessTenant($selectedTenantId)) {
            TenantHelper::setSelectedTenantId($user->accessibleTenants()->first()?->id);
            TenantHelper::setSelectedApp(null);
        }

        return $next($request);
    }
}
