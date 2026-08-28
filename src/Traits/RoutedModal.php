<?php

namespace Noerd\Traits;

/**
 * Shared handling for components that are reachable through BOTH a full-page
 * route and a routed modal (`Noerd::modalRoute()` / `$modalRoute()`): opening
 * such a component as a modal rewrites the browser URL to its route + ?modal=true,
 * so that URL must reproduce the modal when it is pasted into a fresh tab.
 *
 * Used by NoerdPage (details/pages, keyed by {modelId}) and NoerdList (lists that
 * ARE a record, e.g. the object of the custom-attribute manager keyed by {table}).
 */
trait RoutedModal
{
    /**
     * A full page opened with ?modal=true (the URL a routed modal writes while it
     * is open) redirects back to the page the user last visited in this session —
     * whatever it is — and reopens the record as a modal OVER that page via a
     * flashed instruction the noerd-modal stack consumes on mount. Without a
     * previous page (fresh session, reload of the link itself) the plain full page
     * renders. Only applies to real page loads: inside a modal the component mounts
     * during a Livewire request (X-Livewire header) and is never redirected.
     *
     * Returns true when a redirect was issued and mounting should stop.
     */
    protected function redirectToRoutedModal(): bool
    {
        if (($this->embedded ?? false) || request()->hasHeader('X-Livewire') || !request()->boolean('modal')) {
            return false;
        }

        $previousUrl = session()->previousUrl();

        if (!$previousUrl || $previousUrl === request()->fullUrl()) {
            return false;
        }

        session()->flash('noerd-modal.open', [
            'component' => $this->getName(),
            'arguments' => $this->routedModalArguments(),
            'url' => request()->fullUrl(),
        ]);

        $this->redirect($previousUrl);

        return true;
    }

    /**
     * The identity to reopen the modal with: every parameter of the route that
     * produced this page which the component actually binds (e.g. `modelId` of a
     * detail route, `table` of the object route). The `new` sentinel of a create
     * modal maps back to null.
     *
     * @return array<string, mixed>
     */
    protected function routedModalArguments(): array
    {
        $parameters = array_map(
            fn(mixed $value): mixed => $value === 'new' ? null : $value,
            array_filter(
                request()->route()?->parameters() ?? [],
                fn(string $name): bool => property_exists($this, $name),
                ARRAY_FILTER_USE_KEY,
            ),
        );

        if (!array_key_exists('modelId', $parameters) && property_exists($this, 'modelId')) {
            $parameters['modelId'] = $this->modelId;
        }

        return $parameters;
    }
}
