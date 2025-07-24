<button
    {{ $attributes->whereDoesntStartWith('class') }}
    {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex gap-2 items-center px-4 py-2 bg-red-200 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-black focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150']) }}>
    @isset($icon)
        <x-dynamic-component class="w-5 h-5" :component="$icon"/>
    @endisset
    {{ $slot }}
</button>
