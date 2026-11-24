<?php

use Livewire\Component;

new class extends Component {
    public array $model = ['body' => ''];

    public bool $readonly = false;

    public bool $withPlaceholders = true;

    /** @return array<string, string> */
    public function zzLetterPlaceholders(): array
    {
        return ['{{name}}' => 'Contact person', '{{city}}' => 'City'];
    }
}; ?>

<div>
    @include('noerd::components.detail.block', [
        'fields' => [
            array_filter([
                'name' => 'model.body',
                'label' => 'Letter',
                'type' => 'richText',
                'colspan' => 12,
                'readonly' => $readonly,
                'placeholdersMethod' => $withPlaceholders ? 'zzLetterPlaceholders' : null,
            ], fn($value) => $value !== null),
        ],
    ])
</div>
