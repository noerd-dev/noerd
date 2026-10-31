<?php

declare(strict_types=1);

namespace Noerd\Services;

/**
 * Custom looks of list cells. A module registers a key and the Blade view
 * that renders it; a list column opts in with `cell: {key}` next to its
 * `type`. The type keeps deciding filter, sort, alignment, CSV and the
 * formatted text — the renderer only decides the markup of the cell.
 */
final class ListCellRendererRegistry
{
    /** @var array<string, string> */
    private array $views = [];

    public function register(string $key, string $view): void
    {
        $this->views[$key] = $view;
    }

    public function has(string $key): bool
    {
        return isset($this->views[$key]);
    }

    public function get(string $key): ?string
    {
        return $this->views[$key] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->views;
    }
}
