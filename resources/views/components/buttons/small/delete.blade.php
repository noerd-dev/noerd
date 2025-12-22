<button type="button"
    {{ $attributes->whereDoesntStartWith('class') }}
    {{ $attributes->merge(['class' => 'my-auto gap-2 inline-flex items-center rounded-sm border border-gray-300 !bg-red-200 px-2.5 py-1.5 text-xs text-gray-700 shadow-xs hover:bg-red-300 focus:outline-hidden focus:ring-2 focus:ring-red-500 focus:ring-offset-2']) }}>
    <x-noerd::icons.trash/>
    {{ $slot }}
</button>
