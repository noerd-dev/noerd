<?php

use Livewire\Volt\Component;

new class extends Component {
    public array $model = [];

    public function mount(array $initialContent = []): void
    {
        $this->model = [
            'content' => $initialContent,
        ];
    }
}; ?>

<div>
    @php
        $pageLayout = [
            'fields' => [
                ['name' => 'model.content', 'label' => 'Content', 'type' => 'translatableRichText', 'colspan' => 12],
            ],
        ];
    @endphp

    @include('noerd::components.forms.translatable-rich-text', [
        'field' => $pageLayout['fields'][0],
    ])
</div>
