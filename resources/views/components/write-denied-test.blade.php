<?php

use Livewire\Component;
use Noerd\Helpers\AccessHelper;
use Noerd\Models\NoerdUser;

/**
 * Test-only component (WriteDeniedReadonlyTest): renders a synthetic layout
 * through the real detail block with the same canWriteObject() contract as
 * NoerdPage, so the write-denied readonly mechanics can be proven without
 * asserting any shipped YAML configuration.
 */
new class extends Component {
    public $detailModel = NoerdUser::class;

    public array $model = [];

    public string $theme = 'default';

    public function canWriteObject(): bool
    {
        return AccessHelper::canWriteObject($this->detailModel ?? null);
    }

    /** @return array<string, string> */
    public function resolvePicklistOptions(string $picklistField): array
    {
        return ['one' => 'One', 'two' => 'Two'];
    }
}; ?>

<div>
    @include('noerd::components.detail.block', [
        'theme' => $theme,
        'fields' => [
            ['name' => 'model.title', 'label' => 'Title', 'type' => 'text', 'colspan' => 6],
            ['name' => 'model.status', 'label' => 'Status', 'type' => 'select', 'colspan' => 6,
                'options' => [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']]],
            ['name' => 'model.stage', 'label' => 'Stage', 'type' => 'picklist', 'picklistField' => 'stage', 'colspan' => 6],
            ['name' => 'model.active', 'label' => 'Active', 'type' => 'checkbox', 'colspan' => 6],
            ['name' => 'model.notes', 'label' => 'Notes', 'type' => 'textarea', 'colspan' => 12],
            ['name' => 'model.description', 'label' => 'Description', 'type' => 'richText', 'colspan' => 12],
            ['name' => 'doSomething', 'label' => 'Do Something', 'type' => 'button', 'colspan' => 6],
            ['type' => 'block', 'title' => 'Nested', 'colspan' => 12, 'fields' => [
                ['name' => 'model.nested', 'label' => 'Nested Field', 'type' => 'text', 'colspan' => 6],
            ]],
        ],
    ])
</div>
