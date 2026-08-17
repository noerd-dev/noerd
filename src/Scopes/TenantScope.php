<?php

declare(strict_types=1);

namespace Noerd\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Noerd\Helpers\NoerdAuth;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Resolve the user through noerd's own guard: on a host route where a
        // different guard is the default (e.g. Nova), the default guard's user
        // must never influence — or bypass — tenant scoping.
        $user = NoerdAuth::user();

        if ($user && $user->selected_tenant_id) {
            $builder->where($model->getTable() . '.tenant_id', $user->selected_tenant_id);
        }
    }
}
