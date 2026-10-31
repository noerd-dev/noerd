<?php

declare(strict_types=1);

namespace Noerd\Contracts;

/**
 * A model with ONE row per tenant (settings, counters). Implemented by using
 * `Noerd\Traits\TenantSingleton`; the contract is what the settings page and
 * other generic code key off.
 */
interface TenantSingletonContract
{
    /**
     * The attribute values a tenant without a row behaves with.
     *
     * @return array<string, mixed>
     */
    public static function tenantDefaults(): array;

    /**
     * The tenant's row, or an unsaved instance carrying the defaults.
     */
    public static function forTenant(?int $tenantId = null): static;

    public static function existsForTenant(?int $tenantId = null): bool;

    /**
     * Fill and save the tenant's row — created on the first call.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function updateForTenant(array $attributes, ?int $tenantId = null): static;

    public static function forgetTenantSingletons(): void;
}
