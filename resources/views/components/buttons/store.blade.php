<button type="button"
    {{ $attributes->whereDoesntStartWith('class') }}
    {{ $attributes->merge(['class' => 'my-auto gap-2 h-9 inline-flex items-center text-white rounded-sm bg-indigo-600 px-4 py-1.5 text-sm shadow-xs hover:bg-black']) }}>
    <x-noerd::icons.check-circle wire:loading.remove wire:target="store" class="w-5 h-5" />

    <svg wire:loading wire:target="store" class="animate-spin h-5 w-5 text-white"
         xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor"
              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>

    {{ $slot }}
</button>
