<?php

declare(strict_types=1);

namespace Noerd\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (Auth::check() && Auth::user()->selected_tenant_id) {
            $builder->where($model->getTable() . '.tenant_id', Auth::user()->selected_tenant_id);
        }
    }
}
