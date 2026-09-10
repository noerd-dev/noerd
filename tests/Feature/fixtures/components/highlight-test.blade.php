<?php

use Livewire\Component;

/**
 * Test-only component (DetailHighlightTest): renders a synthetic layout through
 * the real detail block, so the highlight ring and the "was: …" line can be
 * proven without asserting any shipped YAML configuration.
 */
new class extends Component {
    public array $model = ['plain' => 'a', 'proposed' => 'b', 'changed' => 'c'];

    public bool $withPrevious = false;

    public string $theme = 'default';
}; ?>

<div>
    @include('noerd::components.detail.block', [
        'theme' => $theme,
        'detailData' => $model,
        'fields' => [
            ['name' => 'model.plain', 'label' => 'Plain', 'type' => 'text', 'colspan' => 6],
            ['name' => 'model.proposed', 'label' => 'Proposed', 'type' => 'text', 'colspan' => 6, 'highlight' => true],
            ['type' => 'block', 'title' => 'Nested', 'colspan' => 12, 'fields' => [
                array_merge(
                    ['name' => 'model.changed', 'label' => 'Changed', 'type' => 'textarea', 'colspan' => 12, 'highlight' => true],
                    $withPrevious ? ['previousValue' => 'the old value'] : [],
                ),
            ]],
        ],
    ])
</div>
