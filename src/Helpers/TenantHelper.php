<?php

declare(strict_types=1);

namespace Noerd\Helpers;

use Noerd\Models\Tenant;

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

    /** Request memo of the single-tenant fallback (false = looked up, none). */
    private static int|false|null $singleTenantId = null;

    /**
     * The tenant the current request operates in — the SINGLE source both the
     * tenant scope and the tenant stamp read, so a record is never written with a
     * different tenant than the one it is later filtered by.
     *
     * Resolves to the authenticated user's selected tenant. Null outside any
     * tenant context: console commands, queue workers and unauthenticated web
     * requests, which stay unscoped.
     */
    public static function currentTenantId(): ?int
    {
        return NoerdAuth::user()?->selected_tenant_id;
    }

    /**
     * The tenant selected in the session. A single-tenant installation has
     * exactly one tenant, which is the answer whenever nothing is selected —
     * resolved read-only (memoized per request), the session is only written
     * by setSelectedTenantId().
     */
    public static function getSelectedTenantId(): ?int
    {
        $id = session('noerd.selected_tenant_id');

        if (! $id && ! config('noerd.features.multi_tenant')) {
            self::$singleTenantId ??= (Tenant::query()->value('id') ?? false);
            $id = self::$singleTenantId ?: null;
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
     */
    public static function getSelectedTenant(): ?Tenant
    {
        $tenantId = self::getSelectedTenantId();

        if (! $tenantId) {
            return null;
        }

        if (! array_key_exists($tenantId, self::$tenantMemo)) {
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
        self::$singleTenantId = null;
    }

    /**
     * Get the selected app from session.
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
}
