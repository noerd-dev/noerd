<?php

use Livewire\Component;

new class extends Component {
    public array $model = [];

    public function mount(array $initialModel = []): void
    {
        $this->model = $initialModel;
    }
}; ?>

<div>
    @php
        $pageLayout = [
            'fields' => [
                ['name' => 'image', 'label' => 'Image', 'type' => 'image', 'colspan' => 6],
            ],
        ];
        $model = $this->model;
    @endphp

    @include('noerd::components.detail.block', [
        'fields' => $pageLayout['fields'],
        'model' => $model,
    ])
</div>
