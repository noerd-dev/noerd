<?php

declare(strict_types=1);

namespace Noerd\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\Tenant;
use Noerd\Scopes\TenantScope;

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

            if ($tenantId && ! $model->tenant_id) {
                $model->tenant_id = $tenantId;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
