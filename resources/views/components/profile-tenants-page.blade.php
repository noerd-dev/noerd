<?php

use Livewire\Component;

/**
 * Profile area: the companies the person has access to.
 */
new class extends Component {}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>
            {{ __('Tenant Access') }}
        </x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::profile-section>
        <livewire:noerd::profile.tenant-access-display-form />
    </x-noerd::profile-section>
</x-noerd::page>
