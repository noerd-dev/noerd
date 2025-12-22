<button type="submit"
    {{ $attributes->whereDoesntStartWith('class') }}
    {{ $attributes->merge(['class' => 'my-auto gap-2 h-8 inline-flex items-center text-white rounded-sm !bg-black px-4 py-1.5 text-sm shadow-xs hover:bg-gray-800 focus:outline-hidden focus:ring-2 focus:ring-black focus:ring-offset-2']) }}>
    @isset($icon)
        <x-dynamic-component class="w-5 h-5" :component="$icon"/>
    @endisset
    {{ $slot }}
</button>
