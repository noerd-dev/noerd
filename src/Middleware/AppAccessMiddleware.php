<?php

namespace Noerd\Noerd\Middleware;

use Closure;
use Illuminate\Http\Request;
use Noerd\Noerd\Exceptions\NoerdException;
use Symfony\Component\HttpFoundation\Response;

class AppAccessMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $appName): Response
    {
        $user = auth()->user();

        if (! $user) {
            return redirect('/login');
        }

        $tenant = $user->selectedTenant();

        if (! $tenant) {
            return redirect('/');
        }

        $hasApp = $tenant->tenantApps()
            ->whereRaw('LOWER(name) = ?', [strtolower($appName)])
            ->exists();

        if (! $hasApp) {
            throw new NoerdException(
                NoerdException::TYPE_APP_NOT_ASSIGNED,
                appName: strtoupper($appName)
            );
        }

        $user->setting->update(['selected_app' => strtoupper($appName)]);

        return $next($request);
    }
}
