<?php

use Livewire\Component;

new class extends Component {
    public function mount(): void
    {
        if (! config('noerd.features.multi_tenant')) {
            abort(404);
        }
    }
} ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Create New Tenant') }}</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::box>
        <div class="max-w-xl">
            <livewire:setup.create-new-tenant />
        </div>
    </x-noerd::box>
</x-noerd::page>
