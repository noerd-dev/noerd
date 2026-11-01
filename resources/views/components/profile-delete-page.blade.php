<?php

use Livewire\Component;

/**
 * Profile area: deleting the person's own account.
 */
new class extends Component {}; ?>

<x-noerd::profile-layout :title="__('Delete Account')">
    <x-noerd::profile-section>
        <livewire:noerd::profile.delete-user-form />
    </x-noerd::profile-section>
</x-noerd::profile-layout>
