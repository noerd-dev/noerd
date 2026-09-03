<x-noerd::app-layout>
    <div class="p-8">
        <x-noerd::profile-section>
            <livewire:noerd::profile.update-profile-information-form />
        </x-noerd::profile-section>

        <x-noerd::profile-section>
            <livewire:noerd::profile.update-password-form />
        </x-noerd::profile-section>

        <x-noerd::profile-section>
            <livewire:noerd::profile.update-language-form />
        </x-noerd::profile-section>

        <x-noerd::profile-section>
            <livewire:noerd::profile.update-locale-form />
        </x-noerd::profile-section>

        @if (config('noerd.features.multi_tenant'))
            <x-noerd::profile-section>
                <livewire:noerd::profile.tenant-access-display-form />
            </x-noerd::profile-section>
        @endif

        <x-noerd::profile-section>
            <livewire:noerd::profile.delete-user-form />
        </x-noerd::profile-section>
    </div>
</x-noerd::app-layout>
