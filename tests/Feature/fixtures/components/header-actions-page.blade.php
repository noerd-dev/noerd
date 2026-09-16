<?php

use Livewire\Component;
use Noerd\Traits\NoerdPage;

/**
 * Test-only page host (HeaderActionsRenderTest): a NoerdPage embedding the fixture
 * detail chrome-less. With `withFields` it also renders a field grid of its own
 * (the read-only facts block shape) — the only case a page hosts the actions itself.
 */
new class extends Component {
    use NoerdPage;

    public bool $withFields = false;

    public function mount(bool $withFields = false): void
    {
        $this->withFields = $withFields;
        $this->initPage();

        $this->pageLayout = [
            'title' => 'Zz Page',
            'detail' => 'noerd-test::header-actions-detail',
        ];

        if ($withFields) {
            $this->pageLayout['fields'] = [
                ['name' => 'detailData.name', 'label' => 'Name', 'type' => 'text', 'colspan' => 6],
            ];
        }
    }
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>Zz Page Header</x-noerd::modal-title>
    </x-slot:header>

    @if ($withFields)
        <x-noerd::tab-content :layout="$pageLayout" :modelId="$modelId" />
    @endif

    @livewire($pageLayout['detail'], ['modelId' => $modelId, 'embedded' => true], key('embedded-detail'))
</x-noerd::page>
