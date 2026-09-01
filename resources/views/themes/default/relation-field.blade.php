<div>
    <x-noerd::input-label for="{{ $fieldName }}" :value="__($label)" :required="$required" :helpText="$helpText" />
    <div class="flex">
        <input
            class="focus:ring-brand-border block h-8 w-full cursor-pointer appearance-none rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white py-2 ps-3 pe-3 text-base leading-[1.375rem] text-zinc-700 placeholder-zinc-400 shadow-xs read-only:border-b-zinc-200 read-only:text-zinc-500 read-only:placeholder-zinc-400/70 read-only:shadow-none focus:ring-2 focus:ring-offset-2 focus:outline-none sm:text-sm"
            type="text"
            readonly
            id="{{ $fieldName }}"
            value="{{ $displayTitle }}"
            @click="@if($displayTitle) $wire.openDetail() @elseif(! $readonly) $modal('{{ $listComponent }}', {id: {{ $modelId ?: 'null' }}, context: '{{ $this->selectionContext() }}', listActionMethod: 'selectAction'}) @endif"
        />

        @if ($displayTitle && ! $readonly)
            <button
                wire:click="clear"
                class="!mt-0 !ml-1 inline-flex h-8 items-center px-2 text-zinc-400 hover:text-zinc-600"
                type="button"
            >
                <x-noerd::icons.x-mark class="h-5 w-5"></x-noerd::icons.x-mark>
            </button>
        @endif

        @if (! $readonly)
            <x-noerd::button
                @click="$modal('{{ $listComponent }}', {id: {{ $modelId ?: 'null' }}, context: '{{ $this->selectionContext() }}', listActionMethod: 'selectAction'})"
                class="!mt-0 !ml-1 h-8 rounded"
                type="button"
            >
                <x-noerd::icons.magnifying-glass></x-noerd::icons.magnifying-glass>
            </x-noerd::button>
        @endif
    </div>
    <x-noerd::input-error :messages="$errorMessages" class="mt-2" />
</div>
