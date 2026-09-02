<?php

use Livewire\Component;
use Noerd\Traits\NoerdDetail;

/**
 * Test-only detail host (PageChromeLayoutTest): a minimal NoerdDetail with a
 * synthetic tabbed layout. Proves the page-chrome spacing mechanics without
 * asserting the YAML configuration of a shipped component.
 */
new class extends Component {
    use NoerdDetail;

    public function mount(): void
    {
        $this->initDetail();

        $this->pageLayout = [
            'title' => 'Zz Chrome Detail',
            'tabs' => [
                ['number' => 1, 'label' => 'One'],
                ['number' => 2, 'label' => 'Two'],
            ],
            'fields' => [
                ['name' => 'detailData.name', 'label' => 'Name', 'type' => 'text', 'colspan' => 6],
            ],
        ];
    }
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>Zz Chrome Detail</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tabs :layout="$pageLayout" :modelId="$modelId" />
    <x-noerd::tab-content :layout="$pageLayout" :modelId="$modelId" />
</x-noerd::page>
