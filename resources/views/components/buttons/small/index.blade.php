<button type="button"
        {{ $attributes->whereDoesntStartWith('class') }}
    {{ $attributes->merge(['class' => 'my-auto gap-2 inline-flex items-center rounded-sm border border-gray-300 bg-white px-2.5 py-1.5 text-xs text-gray-700 shadow-xs hover:bg-gray-50 focus:outline-hidden']) }}>
    {{ $slot }}
</button>
