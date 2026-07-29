<?php

namespace Noerd\Services;

use Noerd\Support\DetailViewDefinition;

class DetailViewRegistry
{
    /** @var array<string, DetailViewDefinition> */
    private array $views = [];

    public function register(DetailViewDefinition $view): void
    {
        $this->views[$view->name] = $view;
    }

    /**
     * Resolve a view definition by name. Unknown or missing names fall back to
     * the 'default' definition so a YAML typo never breaks a detail page.
     */
    public function get(?string $name): DetailViewDefinition
    {
        return $this->views[$name] ?? $this->views['default'];
    }

    public function has(string $name): bool
    {
        return isset($this->views[$name]);
    }

    /**
     * @return array<string, DetailViewDefinition>
     */
    public function all(): array
    {
        return $this->views;
    }
}
