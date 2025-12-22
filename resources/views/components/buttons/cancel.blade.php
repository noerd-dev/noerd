<x-noerd::buttons.primary class="bg-gray-500! hover:bg-gray-400!" type="button" {{ $attributes->whereDoesntStartWith('class') }}>
    {{ $slot }}
</x-noerd::buttons.primary>
