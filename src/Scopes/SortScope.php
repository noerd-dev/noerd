<?php

declare(strict_types=1);

namespace Noerd\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Schema;
use Noerd\Services\ListQueryContext;

class SortScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(ListQueryContext::class);
        $sortField = $context->getSortField();
        $sortAsc = $context->getSortAsc();

        // Only apply sort if the column exists on the model's table
        if (! Schema::connection($model->getConnectionName())->hasColumn($model->getTable(), $sortField)) {
            $sortField = 'id';
        }

        $builder->orderBy($sortField, $sortAsc ? 'asc' : 'desc');
    }
}
