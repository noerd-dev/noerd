<?php

use Livewire\Component;
use Noerd\Helpers\TenantHelper;

new class extends Component {
    public function mount(): void
    {
        TenantHelper::setSelectedAppFromRoute();
    }
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>Advanced Components</x-noerd::modal-title>
    </x-slot:header>

    @include('noerd::ui-library-sections.advanced')
</x-noerd::page>
