<button type="button" {{ $attributes->whereDoesntStartWith('class') }}
    {{ $attributes->merge(['class' => 'inline-flex mr-1 shadow-sm items-center rounded-sm border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-black focus:ring-offset-2']) }}>
    @isset($icon)
        <x-dynamic-component class="w-5 h-5 pr-2" :component="$icon"/>
    @endisset
    <span>{{ $slot }}</span>
</button>
