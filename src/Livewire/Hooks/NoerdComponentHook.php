<?php

declare(strict_types=1);

namespace Noerd\Livewire\Hooks;

use Livewire\ComponentHook;

/**
 * Base of every noerd component hook. Livewire instantiates each registered
 * hook for every component on every mount and hydrate; a hook that names the
 * traits it cares about (TRAITS) is skipped for every other component, so a
 * plain Livewire component never carries noerd's list/detail hooks. The traits
 * a component class uses are inspected once per class, not once per hook per
 * request.
 */
abstract class NoerdComponentHook extends ComponentHook
{
    /**
     * The traits a component must use for this hook to attach; empty = every
     * component.
     *
     * @var list<class-string>
     */
    protected const TRAITS = [];

    /** @var array<class-string, list<class-string>> */
    private static array $traitsByClass = [];

    /**
     * Livewire asks this right after setComponent(): a true answer leaves the
     * hook off the component for the whole request.
     */
    public function skip(): bool
    {
        if (static::TRAITS === []) {
            return false;
        }

        foreach (static::TRAITS as $trait) {
            if ($this->uses($trait)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The traits of any component — memoized per class for the process.
     *
     * @return list<class-string>
     */
    protected static function componentTraits(object $component): array
    {
        return self::$traitsByClass[$component::class] ??= array_values(class_uses_recursive($component));
    }

    /**
     * Whether the hook's component uses the trait, directly or through another
     * trait (NoerdDetail composes NoerdPage, NoerdSettingsPage composes NoerdDetail).
     *
     * @param  class-string  $trait
     */
    protected function uses(string $trait): bool
    {
        return in_array($trait, self::componentTraits($this->component), true);
    }
}
