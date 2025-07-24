<button
    {{ $attributes->whereDoesntStartWith('class') }}
    {{ $attributes->merge(['type' => 'button', 'class' => 'h-[30px] mr-1.5 inline-flex items-center gap-2 px-4 py-1.5 rounded-md border border-gray-300 text-gray-900 text-sm hover:border-gray-400 transition ease-in-out duration-150 focus:outline-hidden focus:ring-2 focus:ring-black focus:ring-offset-2 disabled:opacity-25']) }}>

    @isset($icon)
        <x-dynamic-component wire:loading.remove class="w-5 h-5" :component="$icon"/>
    @endisset
    {{ $slot }}
</button>
