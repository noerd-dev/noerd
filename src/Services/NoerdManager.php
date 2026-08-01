<?php

namespace Noerd\Services;

use Livewire\Livewire;
use RuntimeException;

class NoerdManager
{
    /**
     * Open a Livewire component in a noerd modal, e.g. 'crm::task-create-modal'.
     * For components reachable via a Route::livewire() route use modalRoute()
     * instead — it also rewrites the browser URL to the route.
     */
    public function modal(string $component, mixed $arguments = [], ?string $position = null, ?string $size = null, bool $quickCreate = false): void
    {
        if ($quickCreate) {
            $arguments = is_array($arguments) ? $arguments : ['modelId' => $arguments];
            $arguments['quickCreate'] = true;
            $size ??= 'narrow';
        }

        $this->dispatchModal([
            'modalComponent' => $component,
            'arguments' => $arguments,
            'position' => $position,
            'size' => $size,
        ]);
    }

    /**
     * Open the component behind a named Route::livewire() route in a noerd modal,
     * e.g. 'crm.account.detail'. The browser URL is rewritten to the route
     * (+ ?modal=true) and restored when the modal closes; route params are filled
     * by name from $arguments (a missing required param — e.g. a new record —
     * opens the modal without a URL rewrite).
     *
     * $fallbackComponent is opened when the route name is not registered, so a
     * caller may reference a route owned by an optional module. $rewriteUrl:false
     * resolves the route but keeps the browser URL — for targets that are not
     * addressable (e.g. a list opened filtered by a parent record).
     */
    public function modalRoute(
        string $routeName,
        mixed $arguments = [],
        ?string $position = null,
        ?string $size = null,
        ?string $fallbackComponent = null,
        bool $rewriteUrl = true,
    ): void {
        $this->dispatchModal([
            'route' => $routeName,
            'modalComponent' => $fallbackComponent,
            'arguments' => $arguments,
            'position' => $position,
            'size' => $size,
            'rewriteUrl' => $rewriteUrl,
        ]);
    }

    /**
     * Open a record by route when a route name is configured, else by component.
     * The single call shape for every place that supports both (list row click,
     * relation fields, relation detail jumps): the route wins when registered,
     * the component is the fallback when the owning module is not installed.
     */
    public function modalFor(
        ?string $routeName,
        ?string $component,
        mixed $arguments = [],
        ?string $position = null,
        ?string $size = null,
        bool $rewriteUrl = true,
    ): void {
        if ($routeName !== null && $routeName !== '') {
            $this->modalRoute($routeName, $arguments, $position, $size, $component, $rewriteUrl);

            return;
        }

        if ($component !== null && $component !== '') {
            $this->modal($component, $arguments, $position, $size);
        }
    }

    /** @param array<string, mixed> $params */
    private function dispatchModal(array $params): void
    {
        $current = Livewire::current();

        if ($current === null) {
            throw new RuntimeException(
                'Noerd modals must be opened from within a Livewire request lifecycle.',
            );
        }

        $current->dispatch('noerdModal', ...$params, source: $current->getName());
    }
}
