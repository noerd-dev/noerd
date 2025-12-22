<button type="button"
    {{ $attributes->whereDoesntStartWith('class') }}
    {{ $attributes->merge(['class' => 'my-auto gap-2 inline-flex items-center rounded-sm px-4 py-1 text-sm border border-gray-300 hover:bg-gray-100']) }}>
    @isset($icon)
        <x-dynamic-component class="w-5 h-5" :component="$icon"/>
    @endisset
    {{ $slot }}
</button>
