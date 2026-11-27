<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Support\Facades\Route;
use Noerd\Helpers\AccessHelper;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\TenantApp;

/**
 * Where a person lands after signing in when they asked for no page: the home
 * with the app tiles — or, for somebody who can open exactly ONE app (an
 * employee who only has the intranet), that app itself. An administrator
 * always lands on the home: they also have the setup.
 */
class LandingPage
{
    /**
     * The relative path for the signed-in user in the selected tenant.
     */
    public static function path(): string
    {
        $home = route('noerd.apps', absolute: false);
        $user = NoerdAuth::user();

        if ($user === null || $user->isAdmin()) {
            return $home;
        }

        $apps = (TenantHelper::getSelectedTenant()?->tenantApps ?? collect())
            ->filter(fn(TenantApp $app): bool => ! $app->pivot->getAttribute('is_hidden')
                && (bool) $app->is_active
                && AccessHelper::canAccessApp($app->name)
                && Route::has((string) $app->route))
            ->values();

        if ($apps->count() !== 1) {
            return $home;
        }

        TenantHelper::setSelectedApp($apps->first()->name);

        return route((string) $apps->first()->route, absolute: false);
    }
}
