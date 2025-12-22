<button type="button"
    {{ $attributes->whereDoesntStartWith('class') }}
    {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 px-4 py-1.5 !bg-red-600 rounded-sm text-sm text-white hover:bg-red-500 active:bg-red-900 transition ease-in-out duration-150 focus:outline-hidden focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:opacity-25']) }}>

    <x-noerd::icons.trash class="w-5 h-5"/>
    {{ $slot }}
</button>
