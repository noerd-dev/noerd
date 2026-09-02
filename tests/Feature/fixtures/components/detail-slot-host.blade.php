<?php

use Livewire\Component;
use Noerd\Models\Tenant;
use Noerd\Traits\NoerdDetail;

/**
 * Test-only detail host (DetailSlotRenderTest): a minimal NoerdDetail with a
 * synthetic layout whose single required field makes store() succeed or fail
 * deterministically, so the detailStored-{name} contract slot children rely on
 * can be proven without a shipped component's YAML configuration.
 */
new class extends Component {
    use NoerdDetail;

    public $detailModel = Tenant::class;

    public function mount(): void
    {
        $this->initDetail();

        $this->pageLayout = [
            'title' => 'Zz Detail Slot Host',
            'fields' => [
                ['name' => 'detailData.name', 'label' => 'Name', 'type' => 'text', 'colspan' => 6, 'required' => true],
            ],
        ];
    }
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>Zz Detail Slot Host</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" :modelId="$modelId" />

    <x-noerd::detail-slot name="zz-below-form" :modelId="$modelId" />
</x-noerd::page>
