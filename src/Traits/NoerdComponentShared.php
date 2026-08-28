<?php

namespace Noerd\Traits;

/**
 * The few members NoerdList and NoerdPage genuinely share. Both traits compose
 * this one; PHP dedupes a method arriving through multiple use-paths of the
 * SAME trait, so a component may use NoerdList and NoerdPage together without
 * a collision — previously the byte-identical copies in each trait made that
 * combination a fatal conflict.
 */
trait NoerdComponentShared
{
    public bool $disableModal = false;

    /**
     * Get the component name (alias for getName).
     */
    public function getComponentName(): string
    {
        return $this->getName();
    }

    public function refreshList(): void
    {
        $this->dispatch('$refresh');
    }
}
