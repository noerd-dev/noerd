<?php

declare(strict_types=1);

namespace Noerd\Noerd\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Noerd\Noerd\Models\Tenant;
use Noerd\Noerd\Scopes\TenantScope;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model): void {
            if (Auth::check() && Auth::user()->selected_tenant_id && ! $model->tenant_id) {
                $model->tenant_id = Auth::user()->selected_tenant_id;
            }
        });
    }

    public function initializeBelongsToTenant(): void
    {
        // Ensure tenant_id is fillable if not guarded
        if (! in_array('tenant_id', $this->fillable ?? []) && ! in_array('*', $this->guarded ?? [])) {
            $this->fillable[] = 'tenant_id';
        }
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
