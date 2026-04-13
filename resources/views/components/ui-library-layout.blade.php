<?php

use Livewire\Attributes\Computed;
use Livewire\Component;
use Noerd\Helpers\TenantHelper;

new class extends Component {
    public function mount(): void
    {
        TenantHelper::setSelectedAppFromRoute();
    }

    public function demoAction(): void
    {
        // No-op for demos
    }

    #[Computed]
    public function demoToolbarButtons(): array
    {
        return [
            ['action' => 'demoAction', 'label' => 'Export', 'heroicon' => 'arrow-down-tray'],
            ['type' => 'separator'],
            ['action' => 'demoAction', 'label' => 'Print', 'heroicon' => 'printer'],
            ['action' => 'demoAction', 'label' => 'Refresh', 'heroicon' => 'arrow-path', 'loading' => 'Loading...'],
        ];
    }
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>Layout Components</x-noerd::modal-title>
    </x-slot:header>

    @include('noerd::ui-library-sections.layout')
</x-noerd::page>
