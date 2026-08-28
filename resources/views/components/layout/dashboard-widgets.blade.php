<?php

use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Noerd\Models\Tenant;
use Symfony\Component\Yaml\Yaml;
use Noerd\Helpers\NoerdAuth;

new class extends Component {
    public array $widgets = [];

    public function mount(): void
    {
        $configPath = base_path('app-configs/dashboard-widgets.yml');
        $config = file_exists($configPath)
            ? (Yaml::parse(file_get_contents($configPath) ?: '') ?? [])
            : [];

        $this->widgets = array_values(array_filter(
            $config['widgets'] ?? [],
            fn ($widget): bool => is_array($widget)
                && isset($widget['component'])
                && (! isset($widget['policy']) || $this->canAccess($widget['policy'])),
        ));
    }

    public function canAccess(string $policy): bool
    {
        $user = NoerdAuth::user();

        // Try gate-based ability first (for abilities defined via Gate::define)
        if (Gate::has($policy)) {
            return (bool) $user?->can($policy);
        }

        // Fall back to policy-based ability (for abilities on model policies)
        return (bool) $user?->can($policy, Tenant::class);
    }
} ?>

<div>
    @if ($widgets !== [])
        <div class="flex flex-wrap">
            @foreach ($widgets as $widget)
                @php
                    $width = max(1, (int) ($widget['width'] ?? 1));
                    $height = max(1, (int) ($widget['height'] ?? 1));
                @endphp
                {{-- Widget sizes are declared in app-tile units (tile = 9rem, gutter = 1.5rem), so a
                     2-wide widget aligns exactly with two app tiles. Inline styles because the values
                     come from YAML at runtime — Tailwind's JIT can never see them. --}}
                <div class="mr-6 mt-6"
                     wire:key="dashboard-widget-{{ $loop->index }}-{{ $widget['component'] }}"
                     style="width: calc({{ $width }} * 9rem + {{ $width - 1 }} * 1.5rem); height: calc({{ $height }} * 9rem + {{ $height - 1 }} * 1.5rem);">
                    <livewire:dynamic-component :component="$widget['component']"
                        :wire:key="'dashboard-widget-component-' . $loop->index . '-' . $widget['component']" />
                </div>
            @endforeach
        </div>
    @endif
</div>
