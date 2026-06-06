<?php

use Livewire\Component;

new class extends Component {
    public array $detailData = [];

    public function mount(array $initialModel = []): void
    {
        $this->detailData = $initialModel;
    }
}; ?>

<div>
    @php
        $pageLayout = [
            'fields' => [
                ['name' => 'image', 'label' => 'Image', 'type' => 'image', 'colspan' => 6],
            ],
        ];
    @endphp

    @include('noerd::components.detail.block', [
        'fields' => $pageLayout['fields'],
        'detailData' => $this->detailData,
    ])
</div>
