<?php

declare(strict_types=1);

namespace Noerd\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Noerd\Services\ListQueryContext;

class SearchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(ListQueryContext::class);
        $search = $context->getSearch();

        if (empty($search)) {
            return;
        }

        if (! method_exists($model, 'getSearchableFields')) {
            return;
        }

        $searchable = $model->getSearchableFields();

        if (empty($searchable)) {
            return;
        }

        $builder->where(function (Builder $query) use ($search, $searchable): void {
            foreach ($searchable as $index => $field) {
                if ($index === 0) {
                    $query->where($field, 'like', '%' . $search . '%');
                } else {
                    $query->orWhere($field, 'like', '%' . $search . '%');
                }
            }
        });
    }
}
