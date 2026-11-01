<?php

declare(strict_types=1);

namespace Noerd\Helpers;

use Noerd\Models\Tenant;
use Noerd\Support\Impersonation;
use Noerd\Support\RequestState;
use Noerd\Support\TenantContext;

final class TenantHelper
{
    // Request memo (RequestState bucket 'noerd.tenant'): the selected tenant is
    // read from a dozen call sites per render and Tenant::find() has no identity
    // map.

    /**
     * The tenant the current request operates in — the SINGLE source both the
     * tenant scope and the tenant stamp read, so a record is never written with a
     * different tenant than the one it is later filtered by.
     *
     * Resolves to the TenantContext (a command, job or public frontend working
     * in a tenant), otherwise to the authenticated user's selected tenant. Null
     * where neither is known — a tenant-scoped query then throws.
     */
    public static function currentTenantId(): ?int
    {
        return TenantContext::current() ?? NoerdAuth::user()?->selected_tenant_id;
    }

    /**
     * The tenant selected in the session — read-only, the session is only
     * written by setSelectedTenantId(). Null while nothing is selected; noerd
     * never falls back to "the first tenant".
     */
    public static function getSelectedTenantId(): ?int
    {
        $id = session('noerd.selected_tenant_id');

        return $id ? (int) $id : null;
    }

    /**
     * Set the selected tenant ID in session and persist to database. While an
     * impersonation runs only the session changes: the persisted selection
     * belongs to the person, not to the administrator acting as them.
     */
    public static function setSelectedTenantId(?int $tenantId): void
    {
        session(['noerd.selected_tenant_id' => $tenantId]);
        self::clearCache();

        if (NoerdAuth::check() && ! Impersonation::active()) {
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

        $memo = &RequestState::bucket('noerd.tenant');

        if (! array_key_exists($tenantId, $memo)) {
            $memo[$tenantId] = Tenant::find($tenantId);
        }

        return $memo[$tenantId];
    }

    /**
     * Drop the memoized tenant model (fresh app boot, changed selection).
     */
    public static function clearCache(): void
    {
        RequestState::forget('noerd.tenant');
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
}
