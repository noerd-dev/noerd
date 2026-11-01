<?php

use Livewire\Component;

/**
 * Profile area: the person's name, e-mail address and password.
 */
new class extends Component {}; ?>

<x-noerd::profile-layout :title="__('Profile and Password')">
    <x-noerd::profile-section>
        <livewire:noerd::profile.update-profile-information-form />
    </x-noerd::profile-section>

    <x-noerd::profile-section>
        <livewire:noerd::profile.update-password-form />
    </x-noerd::profile-section>
</x-noerd::profile-layout>
