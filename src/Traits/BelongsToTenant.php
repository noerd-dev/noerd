<?php

declare(strict_types=1);

namespace Noerd\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Noerd\Exceptions\MissingTenantContextException;
use Noerd\Exceptions\TenantMismatchException;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\Tenant;
use Noerd\Scopes\TenantScope;
use Noerd\Support\TenantContext;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::saving(function (Model $model): void {
            static::guardTenantOfUserWrite($model);
        });

        static::creating(function (Model $model): void {
            // Stamp from the SAME resolver the tenant scope filters by, so a
            // record is never written with a tenant the scope would exclude. It
            // reads noerd's own guard, so a host guard's user (e.g. Nova) never
            // stamps — or skips stamping — the tenant id.
            $tenantId = TenantHelper::currentTenantId();

            if ($tenantId && ! $model->getAttribute('tenant_id')) {
                $model->setAttribute('tenant_id', $tenantId);
            }

            // Without a tenant the record would belong to nobody — refused
            // unless the code said "every tenant" with TenantContext::system().
            if (! $model->getAttribute('tenant_id') && ! TenantContext::isSystem()) {
                throw MissingTenantContextException::forCreate($model::class);
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

    /**
     * While a Livewire component processes a client request of a signed-in noerd
     * user (TenantWriteGuardHook) — no explicit TenantContext and no
     * TenantContext::system() — a record is only written into the tenant the
     * user works in: a foreign tenant_id on create and a changed tenant_id on an
     * existing record are refused. A crafted form payload (`detailData.tenant_id`)
     * must never move a record into another tenant. Headless code (commands, jobs,
     * provisioning), test fixtures and code that writes for another tenant on
     * purpose (inside TenantContext::run()) stay free.
     */
    protected static function guardTenantOfUserWrite(Model $model): void
    {
        if (! TenantContext::inClientRequest() || TenantContext::current() !== null || TenantContext::isSystem() || NoerdAuth::user() === null) {
            return;
        }

        $currentTenantId = TenantHelper::currentTenantId();
        $tenantId = $model->getAttribute('tenant_id');

        if (! $model->exists) {
            if ($tenantId !== null && $tenantId !== '' && (int) $tenantId !== $currentTenantId) {
                throw TenantMismatchException::forCreate($model::class, $tenantId, $currentTenantId);
            }

            return;
        }

        if (! $model->isDirty('tenant_id')) {
            return;
        }

        $original = $model->getOriginal('tenant_id');

        // A legacy row without a tenant may be claimed for the user's own tenant.
        if (($original === null || $original === '') && $tenantId !== null && (int) $tenantId === $currentTenantId) {
            return;
        }

        throw TenantMismatchException::forUpdate($model::class, $original, $tenantId);
    }
}
