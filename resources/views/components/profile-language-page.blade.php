<?php

use Livewire\Component;

/**
 * Profile area: the person's interface language and their number and date format.
 */
new class extends Component {}; ?>

<x-noerd::profile-layout :title="__('Language and Locale')">
    <x-noerd::profile-section>
        <livewire:noerd::profile.update-language-form />
    </x-noerd::profile-section>

    <x-noerd::profile-section>
        <livewire:noerd::profile.update-locale-form />
    </x-noerd::profile-section>
</x-noerd::profile-layout>
