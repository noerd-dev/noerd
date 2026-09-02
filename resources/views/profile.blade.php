<x-noerd::app-layout>
    <div class="p-8">
        <x-noerd::box>
            <div class="max-w-xl">
                <livewire:noerd::profile.update-profile-information-form />
            </div>
        </x-noerd::box>

        <x-noerd::box>
            <div class="max-w-xl">
                <livewire:noerd::profile.update-password-form />
            </div>
        </x-noerd::box>

        <x-noerd::box>
            <div class="max-w-xl">
                <livewire:noerd::profile.update-language-form />
            </div>
        </x-noerd::box>

        <x-noerd::box>
            <div class="max-w-xl">
                <livewire:noerd::profile.update-locale-form />
            </div>
        </x-noerd::box>

        @if (config('noerd.features.multi_tenant'))
            <x-noerd::box>
                <div class="max-w-xl">
                    <livewire:noerd::profile.tenant-access-display-form />
                </div>
            </x-noerd::box>
        @endif

        <x-noerd::box>
            <div class="max-w-xl">
                <livewire:noerd::profile.delete-user-form />
            </div>
        </x-noerd::box>
    </div>
</x-noerd::app-layout>
