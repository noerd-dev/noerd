<?php

use Livewire\Component;
use Noerd\Helpers\StaticConfigHelper;

/**
 * Test fixture: renders a detail block from a YAML config resolved through
 * StaticConfigHelper, so the system-wide theme setting is exercised
 * end to end (YAML -> config layer -> block markup).
 */
new class extends Component {
    public array $model = [];

    public string $detailComponent = '';

    public function mount(string $detailComponent): void
    {
        $this->detailComponent = $detailComponent;
    }
}; ?>

<div>
    @php
        $pageLayout = StaticConfigHelper::getComponentFields($detailComponent);
    @endphp

    @include('noerd::components.detail.block', [
        'fields' => $pageLayout['fields'] ?? [],
        'theme' => $pageLayout['theme'] ?? null,
    ])
</div>
