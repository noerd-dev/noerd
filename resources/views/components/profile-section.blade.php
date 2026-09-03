{{-- One block of the profile page: the shared box chrome plus the reading
     width every profile form uses. --}}
<x-noerd::box {{ $attributes }}>
    <div class="max-w-xl">
        {{ $slot }}
    </div>
</x-noerd::box>
