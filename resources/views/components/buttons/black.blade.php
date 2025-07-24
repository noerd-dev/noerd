<button type="button"
    {{ $attributes->whereDoesntStartWith('class') }}
    {{ $attributes->merge(['class' => 'my-auto gap-2 h-9 inline-flex items-center text-white rounded-sm bg-slate-900 px-4 py-1.5 text-sm shadow-xs hover:bg-slate-800']) }}>
    @isset($icon)
        <x-dynamic-component class="w-5 h-5" :component="$icon"/>
    @endisset
    {{ $slot }}
</button>
