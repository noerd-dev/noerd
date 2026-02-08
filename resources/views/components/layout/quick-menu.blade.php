<?php

use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Noerd\Models\Tenant;
use Symfony\Component\Yaml\Yaml;

new class extends Component {
    public $config;

    public function mount()
    {
        // Load quick-menu configuration
        $configPath = base_path('app-configs/quick-menu.yml');
        if (file_exists($configPath)) {
            $content = file_get_contents($configPath);
            $this->config = Yaml::parse($content ?: '');
        } else {
            $this->config = ['buttons' => []];
        }
    }

    public function canAccess($policy)
    {
        $user = auth()->user();

        // Try gate-based ability first (for abilities defined via Gate::define)
        if (Gate::has($policy)) {
            return $user->can($policy);
        }

        // Fall back to policy-based ability (for abilities on model policies)
        return $user->can($policy, Tenant::class);
    }
} ?>

<div class="flex gap-x-2">
    @foreach($config['buttons'] ?? [] as $button)
        @if(!isset($button['policy']) || $this->canAccess($button['policy']))
            <livewire:dynamic-component :component="$button['component']" />
        @endif
    @endforeach
</div>
