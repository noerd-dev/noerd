<button type="button"
        {{ $attributes->whereDoesntStartWith('class') }}
    {{ $attributes->merge(['class' => 'my-auto gap-2 inline-flex items-center px-2.5 py-1 text-xs text-gray-700 hover:bg-gray-50 focus:outline-hidden']) }}>
    <x-noerd::icons.pencil />
    {{ $slot }}
</button>
