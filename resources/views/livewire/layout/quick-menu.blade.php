<?php

use Illuminate\Support\Facades\Gate;
use Noerd\Noerd\Models\Tenant;

use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Symfony\Component\Yaml\Yaml;

new class extends Component {

    public $selectedClientId;
    public $openOrders;
    public $domain;
    public $websiteUrl;
    public $config;

    public function mount()
    {
        $user = auth()->user();
        $this->selectedClientId = $user?->selected_tenant_id ?? 0;

        $selectedTenant = $user ? Tenant::find($user->selected_tenant_id) : null;
        $this->domain = $selectedTenant?->domain;

        // Compute website URL for CMS access if available
        $tenant = $user?->selectedTenant();
        $hash = $tenant?->hash ?? null;
        if ($user && $user->can('canCms') && !empty($hash)) {
            $this->websiteUrl = url('/index?hash=' . $hash);
        } else {
            $this->websiteUrl = null;
        }

        // Load quick-menu configuration
        $configPath = base_path('content/quick-menu.yml');
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
            @if($button['type'] === 'button')
                <div class="hidden lg:flex">
                    <a class="flex cursor-pointer" @if(isset($button['modal']))
                        wire:click="$dispatch('noerdModal', {component: '{{ $button['modal']['component'] }}', arguments: {{ json_encode($button['modal']['arguments'] ?? []) }}})"
                       @endif>
                        <button class="bg-gray-100 rounded-lg my-auto text-sm px-3 py-1">
                            {{ $button['label'] }}
                        </button>
                    </a>
                </div>

            @elseif($button['type'] === 'external_link')
                <div class="hidden lg:flex">
                    <a class="flex" target="_blank" href="{{ $this->{$button['url_property']} ?? '#' }}">
                        <button class="bg-gray-100 rounded-lg my-auto text-sm px-3 py-1">
                            {{ $button['label'] }}
                        </button>
                    </a>
                </div>

            @elseif($button['type'] === 'livewire_component')
                <livewire:dynamic-component :component="$button['component']" />
            @endif
        @endif
    @endforeach
</div>
