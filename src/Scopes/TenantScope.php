<?php

declare(strict_types=1);

namespace Noerd\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // The SAME resolver the creating hook stamps from, so scoping and
        // stamping can never diverge (a record filtered by a tenant it was not
        // written with would silently disappear).
        $tenantId = TenantHelper::currentTenantId();

        if ($tenantId) {
            $builder->where($model->getTable() . '.tenant_id', $tenantId);

            return;
        }

        // Inside a tenant context but without a resolved tenant, yield NO rows
        // rather than every tenant's: a tenant-less account, or a guest on a
        // public app whose tenant could not be resolved. Console commands and
        // queue workers carry no such context and stay unscoped.
        if (NoerdAuth::check() || TenantHelper::isPublicAppGuest()) {
            $builder->whereRaw('1 = 0');
        }
    }
}
