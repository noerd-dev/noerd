<?php

use Livewire\Component;

/**
 * Profile area: the companies the person has access to.
 */
new class extends Component {}; ?>

<x-noerd::profile-layout :title="__('Tenant Access')">
    <x-noerd::profile-section>
        <livewire:noerd::profile.tenant-access-display-form />
    </x-noerd::profile-section>
</x-noerd::profile-layout>
