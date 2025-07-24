<?php

namespace Nywerk\Noerd\Controllers;

use App\Http\Controllers\Controller;

class StartController extends Controller
{
    public function __invoke(): \Illuminate\Http\RedirectResponse
    {
        $route = auth()->user()->selectedTenant()?->tenantApps->first()->route;

        return redirect()->route($route);
    }
}
