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
                ['name' => 'model.content', 'label' => 'Content', 'type' => 'richText', 'colspan' => 12],
            ],
        ];
    @endphp

    @include('noerd::components.detail.block', [
        'fields' => $pageLayout['fields'],
    ])
</div>
