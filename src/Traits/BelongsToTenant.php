<?php

declare(strict_types=1);

namespace Noerd\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Noerd\Helpers\NoerdAuth;
use Noerd\Models\Tenant;
use Noerd\Scopes\TenantScope;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model): void {
            // Resolve through noerd's own guard so a host guard's user (e.g.
            // Nova) never stamps — or skips stamping — the tenant id.
            $user = NoerdAuth::user();

            if ($user && $user->selected_tenant_id && ! $model->tenant_id) {
                $model->tenant_id = $user->selected_tenant_id;
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
