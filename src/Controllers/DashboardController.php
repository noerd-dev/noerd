<?php

namespace Noerd\Controllers;

use Illuminate\Http\RedirectResponse;
use Noerd\Helpers\AccessHelper;

class DashboardController
{
    public function __invoke(): RedirectResponse
    {
        $route = auth()->user()->selectedTenant()?->tenantApps
            ->first(fn($tenantApp) => AccessHelper::canAccessApp($tenantApp->name))
            ?->route;

        if (!$route) {
            $route = 'noerd-apps';
        }

        return redirect()->route($route);
    }
}
