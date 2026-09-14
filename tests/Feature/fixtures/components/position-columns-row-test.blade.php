<?php

use Livewire\Component;
use Noerd\Traits\NoerdPositionRow;

/**
 * Synthetic fixture for PositionColumnsTest: a row component of a configurable
 * position table. The position model and the resolved columns come from the
 * test, never from a module's shipped configuration.
 */
new class extends Component
{
    use NoerdPositionRow;

    public function mount(string $modelClass, int $positionId, array $columns, string $theme = 'default', ?int $number = null): void
    {
        $this->initPositionRow($modelClass::findOrFail($positionId), $columns, $theme, $number);
    }

    public function store(): void
    {
        // A module writes its locked/computed fields itself, then fills the rest.
        $this->position->fill($this->editablePositionValues())->save();
    }

    public function calcGross(): void
    {
        $this->position->price = (float) ($this->row['price'] ?? 0);
        $this->store();
    }
}; ?>

<x-noerd::positions.row :theme="$theme" :number="$number" :colspan="$this->positionColumnCount()">
    <x-noerd::positions.cells :theme="$theme" :columns="$columns" />

    <x-noerd::positions.cell :theme="$theme" width="w-16">
        <button type="button" wire:click="delete" wire:confirm="Delete?">trash</button>
    </x-noerd::positions.cell>
</x-noerd::positions.row>
