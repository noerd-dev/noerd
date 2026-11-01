@props([
    /** The page title, shown in the page header. */
    'title' => null,
])

{{-- The chrome of a profile page: <x-noerd::page> with the title in its header and
     the spacing every page of the profile area shares. The navigation is the sidebar
     (app-configs/profile/navigation.yml, selected by the `profile` route middleware).
     Every profile page — the core's and a module's — is a full-page Livewire component
     that renders it as its root, with one <x-noerd::profile-section> per block. --}}
<x-noerd::page>
    @if ($title)
        <x-slot:header>
            <x-noerd::modal-title>
                {{ $title }}
            </x-noerd::modal-title>
        </x-slot:header>
    @endif

    <div {{ $attributes->merge(['class' => 'space-y-6']) }}>
        {{ $slot }}
    </div>
</x-noerd::page>
