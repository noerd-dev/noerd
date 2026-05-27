<?php

namespace Noerd\Services;

use Livewire\Livewire;
use RuntimeException;

class NoerdManager
{
    public function modal(string $component, mixed $arguments = [], ?string $position = null): void
    {
        $current = Livewire::current();

        if ($current === null) {
            throw new RuntimeException(
                'Noerd::modal() must be called from within a Livewire request lifecycle.'
            );
        }

        $current->dispatch(
            event: 'noerdModal',
            modalComponent: $component,
            source: $current->getName(),
            arguments: $arguments,
            position: $position,
        );
    }
}
