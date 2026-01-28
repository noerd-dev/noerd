<?php

declare(strict_types=1);

namespace Noerd\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Noerd\Services\ListQueryContext;

class SortScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(ListQueryContext::class);
        $sortField = $context->getSortField();
        $sortAsc = $context->getSortAsc();

        $builder->orderBy($sortField, $sortAsc ? 'asc' : 'desc');
    }
}
