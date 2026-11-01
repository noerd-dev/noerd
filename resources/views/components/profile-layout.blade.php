{{-- The content area of a profile page: the spacing every page of the profile
     area shares. The navigation is the sidebar (app-configs/profile/navigation.yml,
     selected by the `profile` route middleware). The core's route views wrap it
     in <x-noerd::app-layout>; a module's full-page Livewire component renders it
     as its root, with one <x-noerd::profile-section> per block. --}}
<div {{ $attributes->merge(['class' => 'space-y-6 p-8']) }}>
    {{ $slot }}
</div>
