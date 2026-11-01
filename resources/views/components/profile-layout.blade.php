{{-- The chrome of a profile page: <x-noerd::page> with the spacing every page of the
     profile area shares. The navigation is the sidebar (app-configs/profile/navigation.yml,
     selected by the `profile` route middleware). Every profile page — the core's and a
     module's — is a full-page Livewire component that renders it as its root: the title
     in <x-slot:header> with <x-noerd::modal-title> (forwarded to the page header, so it
     also shows when the page is opened in a modal), one <x-noerd::profile-section> per block. --}}
<x-noerd::page>
    @isset($header)
        <x-slot:header>
            {{ $header }}
        </x-slot:header>
    @endisset

    <div {{ $attributes->merge(['class' => 'space-y-6']) }}>
        {{ $slot }}
    </div>
</x-noerd::page>
