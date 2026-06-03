<?php

use Livewire\Component;

new class extends Component {
    public array $model = [];

    public bool $compact = false;

    public function mount(array $initialModel = [], bool $compact = false): void
    {
        $this->model = $initialModel;
        $this->compact = $compact;
    }
}; ?>

<div>
    @php
        $pageLayout = [
            'compact' => $compact,
            'fields' => [
                ['name' => 'model.title', 'label' => 'Title', 'type' => 'text', 'colspan' => 6],
                ['name' => 'model.status', 'label' => 'Status', 'type' => 'select', 'colspan' => 6,
                    'options' => [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']]],
                ['name' => 'model.notes', 'label' => 'Notes', 'type' => 'textarea', 'colspan' => 12],
            ],
        ];
    @endphp

    @include('noerd::components.detail.block', [
        'fields' => $pageLayout['fields'],
        'compact' => $pageLayout['compact'],
    ])
</div>
