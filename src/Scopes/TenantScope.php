<?php

declare(strict_types=1);

namespace Noerd\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Noerd\Exceptions\MissingTenantContextException;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Support\TenantContext;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // TenantContext::system() is the explicit "every tenant" mode.
        if (TenantContext::isSystem()) {
            return;
        }

        // The SAME resolver the creating hook stamps from, so scoping and
        // stamping can never diverge (a record filtered by a tenant it was not
        // written with would silently disappear).
        $tenantId = TenantHelper::currentTenantId();

        if ($tenantId) {
            $builder->where($model->getTable() . '.tenant_id', $tenantId);

            return;
        }

        // Inside a tenant context but without a resolved tenant, yield NO rows
        // rather than every tenant's — a tenant-less account.
        if (NoerdAuth::check()) {
            $builder->whereRaw('1 = 0');

            return;
        }

        // No user and no TenantContext (console, queue, public frontends): a
        // query without a tenant would see every tenant's rows — refused. The
        // code names its tenant (TenantContext::run(), ofTenant()) or says
        // "every tenant" (TenantContext::system(), acrossTenants()).
        throw MissingTenantContextException::forQuery($model::class);
    }
}
