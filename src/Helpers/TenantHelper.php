<?php

namespace Noerd\Helpers;

use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;

class TenantHelper
{
    /**
     * Request memo for getSelectedTenant(): the selected tenant is read from a
     * dozen call sites per render (config discovery, middleware, blades) and
     * Tenant::find() has no identity map. Flushed by the provider's booted()
     * hook and whenever the selection changes.
     *
     * @var array<int, Tenant|null>
     */
    private static array $tenantMemo = [];

    /**
     * The tenant the current request operates in — the SINGLE source both the
     * tenant scope and the tenant stamp read, so a record is never written with a
     * different tenant than the one it is later filtered by.
     *
     * Resolves to the authenticated user's selected tenant, or (for a guest) the
     * tenant a public app established. Null outside any tenant context: console
     * commands, queue workers and plain web requests, which stay unscoped.
     */
    public static function currentTenantId(): ?int
    {
        $user = NoerdAuth::user();

        if ($user) {
            return $user->selected_tenant_id;
        }

        return self::getGuestTenantId();
    }

    /**
     * The tenant a public app established for an unauthenticated visitor. Set
     * exclusively by PublicAppMiddleware — never in console or queue context, so
     * background work keeps its unscoped behaviour.
     */
    public static function getGuestTenantId(): ?int
    {
        return session('noerd.guest_tenant_id');
    }

    public static function setGuestTenantId(?int $tenantId): void
    {
        session(['noerd.guest_tenant_id' => $tenantId]);
    }

    /**
     * Whether this request is an unauthenticated visitor served by a public app.
     * Set exclusively by PublicAppMiddleware, so console commands and queue
     * workers — which may well carry a selected app in the session — are never
     * mistaken for a guest browsing tenant data.
     */
    public static function isPublicAppGuest(): bool
    {
        return (bool) session('noerd.public_app_guest', false);
    }

    public static function markPublicAppGuest(): void
    {
        session(['noerd.public_app_guest' => true]);
    }

    /**
     * Get the selected tenant ID from session.
     * Works for both authenticated users and guests.
     */
    public static function getSelectedTenantId(): ?int
    {
        $id = session('noerd.selected_tenant_id');

        if (! $id && ! config('noerd.features.multi_tenant')) {
            $id = Tenant::first()?->id;
            if ($id) {
                session(['noerd.selected_tenant_id' => $id]);
            }
        }

        return $id;
    }

    /**
     * Set the selected tenant ID in session and persist to database.
     */
    public static function setSelectedTenantId(?int $tenantId): void
    {
        session(['noerd.selected_tenant_id' => $tenantId]);
        self::clearCache();

        if (NoerdAuth::check()) {
            NoerdAuth::user()->setting->update(['selected_tenant_id' => $tenantId]);
        }
    }

    /**
     * Get the selected Tenant model (memoized per request).
     * Works for both authenticated users and guests.
     */
    public static function getSelectedTenant(): ?Tenant
    {
        $tenantId = self::getSelectedTenantId();

        if (!$tenantId) {
            return null;
        }

        if (!array_key_exists($tenantId, self::$tenantMemo)) {
            self::$tenantMemo[$tenantId] = Tenant::find($tenantId);
        }

        return self::$tenantMemo[$tenantId];
    }

    /**
     * Drop the memoized tenant model (fresh app boot, changed selection).
     */
    public static function clearCache(): void
    {
        self::$tenantMemo = [];
    }

    /**
     * Get the selected app from session.
     * Works for both authenticated users and guests.
     */
    public static function getSelectedApp(): ?string
    {
        return session('noerd.selected_app');
    }

    /**
     * Set the selected app in session.
     */
    public static function setSelectedApp(?string $appName): void
    {
        // The selected app becomes a PATH SEGMENT in the config resolution
        // (app-configs/{app}/…, navigation.yml), so it must never carry
        // traversal or separator characters — a client-callable openApp() wrote
        // this value straight through.
        if ($appName !== null && ! preg_match('/^[A-Za-z0-9_-]+$/', $appName)) {
            return;
        }

        session(['noerd.selected_app' => $appName]);
    }

    /**
     * Clear the tenant session.
     */
    public static function clear(): void
    {
        session()->forget([
            'noerd.selected_tenant_id',
            'noerd.selected_app',
            'noerd.guest_tenant_id',
            'noerd.public_app_guest',
        ]);
    }

    /**
     * Check if a tenant is selected.
     */
    public static function hasTenant(): bool
    {
        return self::getSelectedTenantId() !== null;
    }

    /**
     * Check if an app is selected.
     */
    public static function hasApp(): bool
    {
        return self::getSelectedApp() !== null;
    }

    /**
     * Set the selected app based on the current route name from tenant_apps.
     */
    public static function setSelectedAppFromRoute(): void
    {
        $currentRoute = request()->route()?->getName();

        if (! $currentRoute) {
            return;
        }

        $app = TenantApp::where('route', $currentRoute)->first();

        if ($app && self::getSelectedApp() !== $app->name) {
            self::setSelectedApp($app->name);
        }
    }
}
