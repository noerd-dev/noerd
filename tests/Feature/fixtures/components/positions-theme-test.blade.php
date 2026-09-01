<?php

use Livewire\Component;

/**
 * Synthetic fixture for PositionsViewTest: a minimal position block built from
 * the generic positions components, so the theme mechanics can be proven without
 * asserting anything about a real module's shipped configuration.
 */
new class extends Component
{
    public string $theme = 'default';

    /** @var array<int, array<string, mixed>> */
    public array $taxes = [];

    public function mount(string $theme = 'default', array $taxes = []): void
    {
        $this->theme = $theme;
        $this->taxes = $taxes;
    }
}; ?>

<div>
    <x-noerd::positions.section :theme="$theme" title="Positions">
        <x-noerd::positions.table
            :theme="$theme"
            :columns="[
                ['label' => 'Quantity', 'class' => 'w-32'],
                ['label' => 'Name', 'class' => 'w-auto'],
                '',
            ]"
        >
            @foreach ([1, 2] as $rowNumber)
                <x-noerd::positions.row :theme="$theme" :number="$rowNumber" :colspan="3">
                    <x-noerd::positions.cell :theme="$theme" width="w-32">
                        <x-noerd::forms.control :theme="$theme" type="number" wire:model="quantity"/>
                    </x-noerd::positions.cell>
                    <x-noerd::positions.cell :theme="$theme" width="w-auto">
                        <x-noerd::forms.control :theme="$theme" type="text" wire:model="name"/>
                    </x-noerd::positions.cell>
                    <x-noerd::positions.cell :theme="$theme" width="w-16"></x-noerd::positions.cell>

                    <x-slot:details>
                        <span>details-{{ $rowNumber }}</span>
                    </x-slot:details>
                </x-noerd::positions.row>
            @endforeach
        </x-noerd::positions.table>

        <x-noerd::positions.totals :theme="$theme" :net="100" :gross="119" :taxes="$taxes"/>
    </x-noerd::positions.section>
</div>
