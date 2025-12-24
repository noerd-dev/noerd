<button type="submit"
    {{ $attributes->whereDoesntStartWith('class') }}
    {{ $attributes->merge(['class' => 'my-auto gap-2 h-8 inline-flex items-center text-white rounded-sm !bg-brand-primary px-4 py-1.5 text-sm shadow-xs hover:bg-brand-primary/80 focus:outline-hidden focus:ring-2 focus:ring-brand-border focus:ring-offset-2']) }}>
    @isset($icon)
        <x-dynamic-component class="w-5 h-5" :component="$icon"/>
    @endisset
    {{ $slot }}
</button>
