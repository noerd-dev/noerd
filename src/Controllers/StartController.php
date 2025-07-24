<?php

namespace Nywerk\Noerd\Controllers;

use App\Http\Controllers\Controller;

class StartController extends Controller
{
    public function __invoke(): \Illuminate\Http\RedirectResponse
    {
        $user = auth()->user();
        if (! $user->selected_tenant_id) {
            $user->selected_tenant_id = $user->tenants->first()->id;
            $user->save();
        }

        $route = auth()->user()->selectedTenant()?->tenantApps->first()->route;

        return redirect()->route($route);
    }
}
