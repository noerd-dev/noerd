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
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model): void {
            if (Auth::check() && Auth::user()->selected_tenant_id && ! $model->tenant_id) {
                $model->tenant_id = Auth::user()->selected_tenant_id;
            }
        });
    }

    public function initializeBelongsToTenant(): void
    {
        // Only add tenant_id to fillable if the model explicitly defines fillable fields
        // If the model uses $guarded instead, we don't need to modify $fillable
        if (! empty($this->fillable) && ! in_array('tenant_id', $this->fillable)) {
            $this->fillable[] = 'tenant_id';
        }
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
