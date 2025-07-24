<button type="submit"
    {{ $attributes->whereDoesntStartWith('class') }}
    {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center gap-2 px-4 py-1.5 !bg-green-600 rounded-xs text-sm text-white hover:bg-green-500 active:bg-green-900 transition ease-in-out duration-150 focus:outline-hidden focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:opacity-25']) }}>

    @isset($icon)
        <x-dynamic-component class="w-5 h-5" :component="$icon"/>
    @endisset
    {{ $slot }}
</button>
