<x-app-layout>

    <div class="p-8">
        <x-noerd::box>
            <div class="max-w-xl">
                <livewire:profile.update-profile-information-form/>
            </div>
        </x-noerd::box>

        <x-noerd::box>
            <div class="max-w-xl">
                <livewire:profile.update-password-form/>
            </div>
        </x-noerd::box>

        <x-noerd::box>
            <div class="max-w-xl">
                <livewire:profile.update-language-form/>
            </div>
        </x-noerd::box>

        <x-noerd::box>
            <div class="max-w-xl">
                <livewire:profile.tenant-access-display-form/>
            </div>
        </x-noerd::box>

        {{--
        <div class="p-4 sm:p-8 bg-white shadow-sm sm:rounded-lg">
            <div class="max-w-xl">
                <livewire:profile.delete-user-form />
            </div>
        </div>
        --}}

    </div>
</x-app-layout>
