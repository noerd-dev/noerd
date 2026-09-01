<?php

use Livewire\Component;
use Noerd\Helpers\AccessHelper;
use Noerd\Helpers\StaticConfigHelper;

new class extends Component {
    public array $widgets = [];

    public function mount(): void
    {
        $configPath = base_path('app-configs/dashboard-widgets.yml');
        $config = file_exists($configPath) ? StaticConfigHelper::parseYamlFile($configPath) : [];

        // The optional `app:` (string) / `apps:` (list) key ties a widget to
        // tenant apps: it renders only when at least one of them is assigned
        // to the tenant AND the app permission allows it — users a restricted
        // app denies must not see its data on the dashboard either.
        $this->widgets = array_values(array_filter(
            $config['widgets'] ?? [],
            function ($widget): bool {
                if (! is_array($widget) || ! isset($widget['component'])) {
                    return false;
                }

                if (isset($widget['policy']) && ! $this->canAccess($widget['policy'])) {
                    return false;
                }

                $apps = array_merge(
                    isset($widget['app']) ? [(string) $widget['app']] : [],
                    array_map('strval', (array) ($widget['apps'] ?? [])),
                );

                return $apps === [] || AccessHelper::canUseApp(...$apps);
            },
        ));
    }

    public function canAccess(string $policy): bool
    {
        return AccessHelper::canPassGate($policy);
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
