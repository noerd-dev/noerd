<?php

use Livewire\Component;
use Noerd\Services\ProfileRegistry;

/**
 * Test-only component (ProfileRegistryTest): renders a `profile` select through
 * the real detail block, fed by the ProfileRegistry — so the registry → picker
 * wiring is proven without asserting any shipped component or YAML.
 */
new class extends Component {
    public array $model = [];
}; ?>

<div>
    @php
        $profileOptions = collect(app(ProfileRegistry::class)->options())
            ->map(fn (string $label, string $key): array => ['value' => $key, 'label' => $label])
            ->values()
            ->all();
    @endphp

    @include('noerd::components.detail.block', [
        'theme' => 'default',
        'fields' => [
            ['name' => 'model.profile', 'label' => 'Profile', 'type' => 'select', 'colspan' => 6, 'options' => $profileOptions],
        ],
    ])
</div>
