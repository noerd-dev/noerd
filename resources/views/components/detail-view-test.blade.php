<?php

use Livewire\Component;

new class extends Component {
    public array $model = [];

    public string $view = 'default';

    public function mount(array $initialModel = [], string $view = 'default'): void
    {
        $this->model = $initialModel;
        $this->view = $view;
    }
}; ?>

<div>
    @php
        $pageLayout = [
            'view' => $view,
            'fields' => [
                ['name' => 'model.title', 'label' => 'Title', 'type' => 'text', 'colspan' => 6],
                ['name' => 'model.status', 'label' => 'Status', 'type' => 'select', 'colspan' => 6,
                    'options' => [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']]],
                ['name' => 'model.amount', 'label' => 'Amount', 'type' => 'currency', 'colspan' => 6, 'number' => 21],
                ['name' => 'model.notes', 'label' => 'Notes', 'type' => 'textarea', 'colspan' => 12],
                ['name' => 'model.plain', 'label' => 'Plain', 'type' => 'text', 'colspan' => 6, 'view' => 'default'],
                ['type' => 'block', 'title' => 'Nested', 'colspan' => 12, 'fields' => [
                    ['name' => 'model.nested', 'label' => 'Nested Field', 'type' => 'text', 'colspan' => 6],
                ]],
            ],
        ];
    @endphp

    @include('noerd::components.detail.block', [
        'fields' => $pageLayout['fields'],
        'view' => $pageLayout['view'],
    ])
</div>
