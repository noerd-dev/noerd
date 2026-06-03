<?php

use Livewire\Component;

new class extends Component {
    public array $model = [];
}; ?>

<div>
    @include('noerd::components.detail.block', [
        'fields' => [
            ['name' => 'model.a', 'label' => 'A', 'type' => 'text', 'colspan' => 6],
            ['type' => 'spacer', 'colspan' => 6],
            ['name' => 'model.b', 'label' => 'B', 'type' => 'text', 'colspan' => 6],
        ],
    ])
</div>
