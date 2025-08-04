<?php

namespace Noerd\Noerd\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetupMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        if (! $user->selected_tenant_id) {
            $user->selected_tenant_id = $user->tenants->first()?->id;
            $user->save();

            if(! $user->tenants->first()) {
               return redirect('no-tenant');
            }
        }

        session(['currentApp' => 'SETUP']);

        if (!Auth::user()->isAdmin()) {
            abort(401);
        }

        return $next($request);
    }
}
