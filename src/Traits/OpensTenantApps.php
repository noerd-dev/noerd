<?php

declare(strict_types=1);

namespace Noerd\Traits;

use Illuminate\Support\Facades\Route;
use Noerd\Helpers\AccessHelper;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\TenantApp;

/**
 * The `openApp($appName)` action of the app bar and the apps overview — the
 * one place a click on an app tile selects the app and navigates to it.
 *
 * @internal
 */
trait OpensTenantApps
{
    /**
     * The target is resolved from the tenant's own app record — never from the
     * request. A client-supplied route would otherwise redirect anywhere. A
     * hidden, inactive or denied app is refused, an app whose route is not
     * registered answers 404.
     */
    public function openApp(string $appName): void
    {
        $tenantApp = TenantHelper::getSelectedTenant()?->tenantApps
            ->first(fn(TenantApp $app): bool => mb_strtolower($app->name) === mb_strtolower($appName));

        abort_unless($tenantApp instanceof TenantApp, 403);
        abort_if((bool) $tenantApp->pivot->getAttribute('is_hidden'), 403);
        abort_unless((bool) $tenantApp->is_active, 403);
        abort_unless(AccessHelper::canAccessApp($tenantApp->name), 403);
        abort_unless(Route::has((string) $tenantApp->route), 404);

        TenantHelper::setSelectedApp($tenantApp->name);
        $this->redirect(route((string) $tenantApp->route), navigate: true);
    }
}
