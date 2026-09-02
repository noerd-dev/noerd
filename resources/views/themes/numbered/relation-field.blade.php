<x-noerd::detail.numbered-row :field="$this->numberedRowField()">
    <div class="flex">
        <input
            class="block h-9 w-full cursor-pointer appearance-none rounded-none border border-zinc-400 bg-white py-1.5 ps-2 pe-2 text-base text-zinc-700 placeholder-zinc-400 read-only:text-zinc-500 read-only:placeholder-zinc-400/70 focus:border-dotted focus:border-zinc-600 focus:ring-0 focus:outline-none sm:text-sm"
            type="text"
            readonly
            id="{{ $fieldName }}"
            value="{{ $displayTitle }}"
            @click="@if($displayTitle) $wire.openDetail() @elseif(! $readonly) $modal('{{ $listComponent }}', {id: {{ $modelId ?: 'null' }}, context: '{{ $this->selectionContext() }}', listActionMethod: 'selectAction'}) @endif"
        />

        @if ($displayTitle && ! $readonly)
            <button
                wire:click="clear"
                aria-label="{{ __('Clear selection') }}"
                class="mt-0! ml-1! inline-flex h-9 items-center px-2 text-zinc-400 hover:text-zinc-600"
                type="button"
            >
                <x-noerd::icons.x-mark class="h-5 w-5"></x-noerd::icons.x-mark>
            </button>
        @endif

        @if (! $readonly)
            <x-noerd::button
                @click="$modal('{{ $listComponent }}', {id: {{ $modelId ?: 'null' }}, context: '{{ $this->selectionContext() }}', listActionMethod: 'selectAction'})"
                aria-label="{{ __('Search') }}"
                class="mt-0! ml-1! h-9! rounded-none px-2!"
                type="button"
            >
                <x-noerd::icons.magnifying-glass></x-noerd::icons.magnifying-glass>
            </x-noerd::button>
        @endif
</div>
    <x-noerd::input-error :messages="$errorMessages" class="mt-2" />
</x-noerd::detail.numbered-row>
