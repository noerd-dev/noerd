<?php

declare(strict_types=1);

namespace Noerd\Traits;

use Noerd\Helpers\TenantHelper;
use Noerd\Scopes\TenantScope;
use Noerd\Support\RequestState;
use Noerd\Support\TenantContext;
use RuntimeException;

/**
 * ONE row per tenant: the settings row of an app, a tenant's counters, the
 * platform credentials of a module. The trait is the single lookup every such
 * model offers — `forTenant()` answers the tenant's row or an unsaved instance
 * carrying `tenantDefaults()`, so a tenant without a row behaves like one with
 * the defaults and callers never branch on null. Memoized per request (the
 * row is read for every cell of a list), dropped on save and delete.
 *
 * The lookup names the tenant explicitly: with no argument it takes the current
 * tenant (TenantHelper::currentTenantId(): the tenant a job or command entered,
 * else the signed-in user's selection) and refuses to guess when there is none.
 *
 * A model using the trait declares `implements TenantSingletonContract`, which
 * is what the settings page and other generic code key off.
 */
trait TenantSingleton
{
    public static function bootTenantSingleton(): void
    {
        static::saved(static function (): void {
            static::forgetTenantSingletons();
        });
        static::deleted(static function (): void {
            static::forgetTenantSingletons();
        });
    }

    /**
     * The attribute values a tenant without a row behaves with. Override in the
     * model (`['default_vacation_days' => 30]`); the unsaved instance carries them.
     *
     * @return array<string, mixed>
     */
    public static function tenantDefaults(): array
    {
        return [];
    }

    /**
     * The tenant's row, or an unsaved instance with the defaults (`exists`
     * false) when the tenant has none yet.
     */
    public static function forTenant(?int $tenantId = null): static
    {
        // The tenant a job or command entered first, then the selection of the
        // signed-in user — the one resolver TenantScope reads as well.
        $tenantId ??= TenantHelper::currentTenantId();

        if ($tenantId === null) {
            throw new RuntimeException(sprintf(
                '%s::forTenant() needs a tenant: none is selected and none was passed.',
                static::class,
            ));
        }

        $memo = &RequestState::bucket(self::tenantSingletonBucket());

        if (isset($memo[$tenantId])) {
            return $memo[$tenantId];
        }

        // Only the tenant filter is lifted — the tenant is named explicitly, and
        // a model without BelongsToTenant has no such scope at all. Every other
        // global scope of the model stays applied.
        $query = static::query()->withoutGlobalScope(TenantScope::class);
        $row = $query->where($query->qualifyColumn('tenant_id'), $tenantId)->first();

        $row ??= static::query()->newModelInstance()->forceFill(['tenant_id' => $tenantId] + static::tenantDefaults());

        return $memo[$tenantId] = $row;
    }

    /**
     * Whether the tenant has a saved row at all.
     */
    public static function existsForTenant(?int $tenantId = null): bool
    {
        return static::forTenant($tenantId)->exists;
    }

    /**
     * Write attributes onto the tenant's row — created on the first call. Mass
     * assignment respects the model's $guarded like any fill().
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function updateForTenant(array $attributes, ?int $tenantId = null): static
    {
        $row = static::forTenant($tenantId);
        $row->fill($attributes);

        // The tenant is named explicitly here, so the write runs in its context:
        // a user request may update another tenant's singleton on purpose (an
        // operator screen, a provisioning step) without tripping the tenant
        // guard of BelongsToTenant.
        TenantContext::run((int) $row->getAttribute('tenant_id'), fn(): bool => $row->save());

        return $row;
    }

    public static function forgetTenantSingletons(): void
    {
        RequestState::forget(self::tenantSingletonBucket());
    }

    private static function tenantSingletonBucket(): string
    {
        return 'noerd.tenant-singleton.' . static::class;
    }
}
