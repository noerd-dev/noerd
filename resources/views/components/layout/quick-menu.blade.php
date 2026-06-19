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

    public function showTenantSwitcher(): bool
    {
        $user = auth()->user();

        return config('noerd.features.multi_tenant')
            && ($user->tenants->count() > 1
                || ($user->isAdmin() && config('noerd.features.new_tenant')));
    }
} ?>

{{-- The tenant switcher stays in a non-overflow row so its dropdown can overlap freely; only the
     YAML buttons scroll horizontally when they get too wide (overflow-x-auto would otherwise clip
     the dropdown vertically as well). --}}
<div class="flex items-center gap-x-2 min-w-0 flex-1">
    @if($this->showTenantSwitcher())
        <div class="shrink-0">
            <livewire:noerd::layout.tenant-switcher />
        </div>
    @endif

    <div class="flex items-center gap-x-2 overflow-x-auto min-w-0 flex-1 p-1">
        @foreach($config['buttons'] ?? [] as $button)
            @if(!isset($button['policy']) || $this->canAccess($button['policy']))
                <div class="shrink-0">
                    <livewire:dynamic-component :component="$button['component']" />
                </div>
            @endif
        @endforeach
    </div>
</div>
