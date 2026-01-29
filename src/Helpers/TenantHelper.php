<?php

namespace Noerd\Helpers;

use Noerd\Models\Tenant;

class TenantHelper
{
    /**
     * Get the selected tenant ID from session.
     * Works for both authenticated users and guests.
     */
    public static function getSelectedTenantId(): ?int
    {
        return session('noerd.selected_tenant_id');
    }

    /**
     * Set the selected tenant ID in session and persist to database.
     */
    public static function setSelectedTenantId(?int $tenantId): void
    {
        session(['noerd.selected_tenant_id' => $tenantId]);

        if (auth()->check()) {
            auth()->user()->setting->update(['selected_tenant_id' => $tenantId]);
        }
    }

    /**
     * Get the selected Tenant model.
     * Works for both authenticated users and guests.
     */
    public static function getSelectedTenant(): ?Tenant
    {
        $tenantId = self::getSelectedTenantId();

        return $tenantId ? Tenant::find($tenantId) : null;
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
        session(['noerd.selected_app' => $appName]);
    }

    /**
     * Clear the tenant session.
     */
    public static function clear(): void
    {
        session()->forget(['noerd.selected_tenant_id', 'noerd.selected_app']);
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
