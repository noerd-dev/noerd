<?php

declare(strict_types=1);

namespace Noerd\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\Tenant;
use Noerd\Scopes\TenantScope;
use Noerd\Support\MissingTenantContext;
use Noerd\Support\TenantContext;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function (Model $model): void {
            // Stamp from the SAME resolver the tenant scope filters by, so a
            // record is never written with a tenant the scope would exclude. It
            // reads noerd's own guard, so a host guard's user (e.g. Nova) never
            // stamps — or skips stamping — the tenant id.
            $tenantId = TenantHelper::currentTenantId();

            if ($tenantId && ! $model->getAttribute('tenant_id')) {
                $model->setAttribute('tenant_id', $tenantId);
            }

            if (! $model->getAttribute('tenant_id') && ! TenantContext::isSystem()) {
                MissingTenantContext::applyToCreate($model::class);
            }
        });
    }

    /**
     * The rows of ONE tenant, whatever the current context — replaces only the
     * tenant filter (soft deletes and the object read guard stay applied).
     * Prefer TenantContext::run() for a whole unit of work.
     */
    public function scopeOfTenant(Builder $query, int $tenantId): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class)
            ->where($query->qualifyColumn('tenant_id'), $tenantId);
    }

    /**
     * The rows of every tenant — explicit, and only the tenant filter is lifted
     * (soft deletes and the object read guard stay applied).
     */
    public function scopeAcrossTenants(Builder $query): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
