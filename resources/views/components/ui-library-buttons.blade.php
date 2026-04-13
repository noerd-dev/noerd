<?php

use Livewire\Component;
use Noerd\Helpers\TenantHelper;

new class extends Component {
    public function mount(): void
    {
        TenantHelper::setSelectedAppFromRoute();
    }

    public function demoAction(): void
    {
        // No-op for button demos
    }
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>Buttons</x-noerd::modal-title>
    </x-slot:header>

    @include('noerd::ui-library-sections.buttons')
</x-noerd::page>
