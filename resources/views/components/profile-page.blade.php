<?php

use Livewire\Component;

/**
 * Profile area: the person's name, e-mail address and password.
 */
new class extends Component {}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>
            {{ __('Profile and Password') }}
        </x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::profile-section>
        <livewire:noerd::profile.update-profile-information-form />
    </x-noerd::profile-section>

    @if (\Noerd\Helpers\NoerdAuth::passwordLoginEnabled())
        <x-noerd::profile-section>
            <livewire:noerd::profile.update-password-form />
        </x-noerd::profile-section>
    @endif
</x-noerd::page>
