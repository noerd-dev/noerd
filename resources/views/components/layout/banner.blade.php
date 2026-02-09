<?php

use Livewire\Attributes\Computed;
use Livewire\Component;
use Symfony\Component\Yaml\Yaml;

new class extends Component {
    public array $banners = [];
    public array $dismissedBanners = [];

    public function mount(): void
    {
        $configPath = base_path('app-configs/banner.yml');
        $config = file_exists($configPath) ? Yaml::parse(file_get_contents($configPath) ?: '') : [];
        $this->banners = collect($config['banners'] ?? [])
            ->filter(fn($banner) => isset($banner['priority']))
            ->sortByDesc('priority')
            ->values()
            ->all();

        $this->dismissedBanners = session('dismissed_banners', []);
    }

    public function dismiss(int $index): void
    {
        $dismissed = session('dismissed_banners', []);
        $dismissed[] = $index;
        session(['dismissed_banners' => $dismissed]);
        $this->dismissedBanners = $dismissed;

        $this->dispatch('banner-dismissed', hasBanner: $this->activeBanner !== null);
    }

    #[Computed]
    public function activeBanner(): ?array
    {
        foreach ($this->banners as $index => $banner) {
            if (!in_array($index, $this->dismissedBanners)) {
                return ['index' => $index, 'banner' => $banner];
            }
        }
        return null;
    }

    #[Computed]
    public function hasBanner(): bool
    {
        return $this->activeBanner !== null;
    }
}; ?>

@php
$styles = [
    'warning' => 'bg-yellow-500 text-yellow-900',
    'danger' => 'bg-red-600 text-white',
    'info' => 'bg-blue-500 text-white',
    'success' => 'bg-green-500 text-white',
];
@endphp

<div
    x-data="{ hasBanner: {{ $this->activeBanner ? 'true' : 'false' }} }"
    x-init="document.documentElement.style.setProperty('--banner-height', hasBanner ? '36px' : '0px')"
    x-effect="document.documentElement.style.setProperty('--banner-height', hasBanner ? '36px' : '0px')"
    @banner-dismissed.window="hasBanner = $event.detail.hasBanner; document.documentElement.style.setProperty('--banner-height', hasBanner ? '36px' : '0px')"
>
    @if($this->activeBanner)
        @php
            $active = $this->activeBanner;
            $banner = $active['banner'];
            $index = $active['index'];
            $bgClass = $styles[$banner['type'] ?? 'warning'] ?? $styles['warning'];
            $dismissible = $banner['dismissible'] ?? false;
        @endphp

        <div class="fixed top-0 left-0 w-full z-50 {{ $bgClass }}">
            <div class="px-4 py-2 text-center text-sm font-medium flex items-center justify-center relative">
                <div class="flex-1 text-center">
                    @if(isset($banner['component']))
                        @livewire($banner['component'])
                    @else
                        {{ $banner['message'] ?? '' }}
                    @endif
                </div>

                @if($dismissible)
                    <button
                        wire:click="dismiss({{ $index }})"
                        type="button"
                        class="absolute right-4 p-1 hover:opacity-70 transition-opacity"
                    >
                        <span class="sr-only">Dismiss</span>
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                @endif
            </div>
        </div>
    @endif
</div>
