<?php

namespace Noerd\Traits;

/**
 * The few members NoerdList and NoerdPage genuinely share. Both traits compose
 * this one, and PHP dedupes a method arriving through multiple use-paths of
 * the SAME trait — a component composing NoerdList and NoerdPage only has to
 * resolve the members both traits define themselves (mount(), getListeners()).
 */
trait NoerdComponentShared
{
    public bool $disableModal = false;

    /**
     * Get the component name (public alias for componentName()).
     */
    public function getComponentName(): string
    {
        return $this->componentName();
    }

    public function refreshList(): void
    {
        $this->dispatch('$refresh');
    }

    /**
     * The name noerd resolves this component's YAML config, session keys and
     * trait events by — Livewire's component name. A test fixture that is not
     * registered under a name overrides it; shipped components never do.
     */
    protected function componentName(): string
    {
        return $this->getName();
    }
}
