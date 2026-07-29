<?php

namespace Noerd\Controllers;

use Illuminate\Http\RedirectResponse;

class DashboardController
{
    public function __invoke(): RedirectResponse
    {
        $route = auth()->user()->selectedTenant()?->tenantApps->first()?->route;

        if (!$route) {
            $route = 'noerd-apps';
        }

        return redirect()->route($route);
    }
}
