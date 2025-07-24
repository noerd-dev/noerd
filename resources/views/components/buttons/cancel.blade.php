<x-noerd::primary-button class="bg-gray-500! hover:bg-gray-400!" type="button" {{ $attributes->whereDoesntStartWith('class') }}>
    {{ $slot }}
</x-noerd::primary-button>
