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
        <x-noerd::modal-title>Labels & Errors</x-noerd::modal-title>
    </x-slot:header>

    @include('noerd::ui-library-sections.labels-errors')
</x-noerd::page>
