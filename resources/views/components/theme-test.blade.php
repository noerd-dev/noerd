<?php

use Livewire\Component;

new class extends Component {
    public array $model = [];

    public string $theme = 'default';

    /** Synthetic layout override; empty keeps the fixture layout below. */
    public array $fields = [];

    public function mount(array $initialModel = [], string $theme = 'default', array $fields = []): void
    {
        $this->model = $initialModel;
        $this->theme = $theme;
        $this->fields = $fields;
    }
}; ?>

<div>
    @php
        $pageLayout = [
            'theme' => $theme,
            'fields' => [
                ['name' => 'model.title', 'label' => 'Title', 'type' => 'text', 'colspan' => 6],
                ['name' => 'model.status', 'label' => 'Status', 'type' => 'select', 'colspan' => 6,
                    'options' => [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']]],
                ['type' => 'spacer', 'colspan' => 12],
                ['name' => 'model.amount', 'label' => 'Amount', 'type' => 'currency', 'colspan' => 6, 'number' => 21],
                ['name' => 'model.notes', 'label' => 'Notes', 'type' => 'textarea', 'colspan' => 12],
                ['name' => 'model.plain', 'label' => 'Plain', 'type' => 'text', 'colspan' => 6, 'theme' => 'default'],
                ['type' => 'block', 'title' => 'Nested', 'colspan' => 12, 'fields' => [
                    ['name' => 'model.nested', 'label' => 'Nested Field', 'type' => 'text', 'colspan' => 6],
                ]],
            ],
        ];

        if ($fields !== []) {
            $pageLayout['fields'] = $fields;
        }
    @endphp

    @include('noerd::components.detail.block', [
        'fields' => $pageLayout['fields'],
        'theme' => $pageLayout['theme'],
    ])
</div>
