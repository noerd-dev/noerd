<?php

use Livewire\Component;

new class extends Component {
    public array $model = [];
}; ?>

<div>
    @include('noerd::components.detail.block', [
        'fields' => [
            ['name' => 'model.phone', 'label' => 'Phone', 'type' => 'phone', 'colspan' => 6],
            ['name' => 'model.email', 'label' => 'Email', 'type' => 'email', 'colspan' => 6],
            ['name' => 'model.roPhone', 'label' => 'RO Phone', 'type' => 'phone', 'readonly' => true, 'colspan' => 6],
        ],
    ])
</div>
