<?php

namespace Noerd\Controllers;

use Illuminate\Http\RedirectResponse;

class DashboardController
{
    public function __invoke(): RedirectResponse
    {
        $route = auth()->user()->selectedTenant()?->tenantApps->first()?->route;

        if (!$route) {
            $route = 'noerd-home';
        }

        return redirect()->route($route);
    }
}
