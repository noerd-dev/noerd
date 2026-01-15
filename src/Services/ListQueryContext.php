<?php

declare(strict_types=1);

namespace Noerd\Noerd\Services;

class ListQueryContext
{
    protected string $search = '';

    protected string $sortField = 'id';

    protected bool $sortAsc = false;

    public function set(string $search, string $sortField, bool $sortAsc): void
    {
        $this->search = $search;
        $this->sortField = $sortField;
        $this->sortAsc = $sortAsc;
    }

    public function getSearch(): string
    {
        return $this->search;
    }

    public function getSortField(): string
    {
        return $this->sortField;
    }

    public function getSortAsc(): bool
    {
        return $this->sortAsc;
    }
}
