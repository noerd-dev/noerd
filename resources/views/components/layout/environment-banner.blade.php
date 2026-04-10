<?php

use Livewire\Component;

new class extends Component {
    public ?string $environment = null;
    public string $label = '';
    public string $bgClass = '';

    public function mount(): void
    {
        $env = app()->environment();

        $config = match ($env) {
            'local' => ['label' => 'Local', 'bgClass' => 'bg-blue-100 text-blue-800'],
            'development' => ['label' => 'Development', 'bgClass' => 'bg-emerald-100 text-emerald-800'],
            'staging' => ['label' => 'Staging', 'bgClass' => 'bg-orange-100 text-orange-800'],
            default => null,
        };

        if ($config !== null) {
            $this->environment = $env;
            $this->label = $config['label'];
            $this->bgClass = $config['bgClass'];
        }
    }
}; ?>

<div
    x-data="{ hasBanner: {{ $environment ? 'true' : 'false' }} }"
    x-init="document.documentElement.style.setProperty('--environment-banner-height', hasBanner ? '36px' : '0px')"
>
    @if($environment)
        <div class="fixed top-0 left-0 w-full z-50 {{ $bgClass }}">
            <div class="px-4 py-2 text-center text-sm font-semibold tracking-wide">
                {{ $label }}
            </div>
        </div>
    @endif
</div>
