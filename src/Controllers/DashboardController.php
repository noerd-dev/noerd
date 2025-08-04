<?php

namespace Noerd\Noerd\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class DashboardController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        $route = auth()->user()->selectedTenant()?->tenantApps->first()->route;

        if (!$route) {
            $route = 'noerd-home';
        }

        return redirect()->route($route);
    }
}
