<?php

use Livewire\Component;

/**
 * Profile area: deleting the person's own account.
 */
new class extends Component {}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>
            {{ __('Delete Account') }}
        </x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::profile-section>
        <livewire:noerd::profile.delete-user-form />
    </x-noerd::profile-section>
</x-noerd::page>
