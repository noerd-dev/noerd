<?php

use Livewire\Component;
use Noerd\Models\NoerdUser;
use Noerd\Traits\NoerdDetail;

/**
 * Test-only detail host (HeaderActionsRenderTest): a hand-built detail that includes
 * the form block DIRECTLY, twice, instead of going through x-noerd::tab-content —
 * the shape of the pdm and harvester details.
 */
new class extends Component {
    use NoerdDetail;

    public ?string $detailPrimary = 'noerdUserId';

    public $detailModel = NoerdUser::class;

    public function mount(): void
    {
        $this->initDetail();

        $this->pageLayout = [
            'title' => 'Zz Direct Block',
            'fields' => [
                ['name' => 'detailData.name', 'label' => 'Name', 'type' => 'text', 'colspan' => 6],
            ],
        ];
    }
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>Zz Direct Header</x-noerd::modal-title>
    </x-slot:header>

    @include('noerd::components.detail.block', array_merge($pageLayout, ['modelId' => $modelId]))
    @include('noerd::components.detail.block', array_merge($pageLayout, ['title' => 'Zz Second Direct Block', 'modelId' => $modelId]))
</x-noerd::page>
