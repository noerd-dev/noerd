<?php

declare(strict_types=1);

namespace Noerd\Traits;

use Illuminate\Database\Eloquent\Builder;
use Noerd\Scopes\SearchScope;
use Noerd\Scopes\SortScope;

trait HasListScopes
{
    public static function bootHasListScopes(): void
    {
        static::addGlobalScope(new SearchScope());
        static::addGlobalScope(new SortScope());
    }

    /**
     * Get the searchable fields for this model.
     */
    public function getSearchableFields(): array
    {
        return $this->searchable ?? [];
    }

    /**
     * Get the sortable fields for this model.
     */
    public function getSortableFields(): array
    {
        return $this->sortable ?? [];
    }

    /**
     * Get the not-sortable fields for this model.
     */
    public function getNotSortableFields(): array
    {
        return $this->notSortable ?? [];
    }

    /**
     * Check if a given field is sortable.
     */
    public function isFieldSortable(string $field): bool
    {
        $sortable = $this->getSortableFields();
        $notSortable = $this->getNotSortableFields();

        if (! empty($sortable)) {
            return in_array($field, $sortable);
        }

        if (! empty($notSortable)) {
            return ! in_array($field, $notSortable);
        }

        return true;
    }

    /**
     * Manual scope to search across searchable fields (for backward compatibility).
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        $searchable = $this->searchable ?? [];

        if (empty($searchable)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($search, $searchable): void {
            foreach ($searchable as $index => $field) {
                if ($index === 0) {
                    $query->where($field, 'like', '%' . $search . '%');
                } else {
                    $query->orWhere($field, 'like', '%' . $search . '%');
                }
            }
        });
    }

    /**
     * Manual scope to sort by field and direction (for backward compatibility).
     */
    public function scopeSorted(Builder $query, ?string $field, bool $ascending = true): Builder
    {
        $sortField = $field ?: $this->getDefaultSortField();
        $direction = $ascending ? 'asc' : 'desc';

        return $query->orderBy($sortField, $direction);
    }

    protected function getDefaultSortField(): string
    {
        return $this->defaultSortField ?? 'id';
    }
}
