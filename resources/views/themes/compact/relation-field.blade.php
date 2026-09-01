<div class="flex items-center gap-2">
    <x-noerd::input-label
        for="{{ $fieldName }}"
        :value="__($label)"
        :required="$required"
        :helpText="$helpText"
        :title="__($label)"
        class="w-36 shrink-0 truncate !pb-0"
    />
    <div class="min-w-0 flex-1">
        <div class="flex">
            <input
                class="focus:ring-brand-border block h-7 w-full cursor-pointer appearance-none rounded-sm border border-zinc-200 bg-white py-1 ps-2 pe-2 text-base text-zinc-700 placeholder-zinc-400 read-only:text-zinc-500 read-only:placeholder-zinc-400/70 focus:ring-1 focus:outline-none sm:text-sm"
                type="text"
                readonly
                id="{{ $fieldName }}"
                value="{{ $displayTitle }}"
                @click="@if($displayTitle) $wire.openDetail() @elseif(! $readonly) $modal('{{ $listComponent }}', {id: {{ $modelId ?: 'null' }}, context: '{{ $this->selectionContext() }}', listActionMethod: 'selectAction'}) @endif"
            />

            @if ($displayTitle && ! $readonly)
                <button
                    wire:click="clear"
                    class="!mt-0 !ml-1 inline-flex h-7 items-center px-2 text-zinc-400 hover:text-zinc-600"
                    type="button"
                >
                    <x-noerd::icons.x-mark class="h-5 w-5"></x-noerd::icons.x-mark>
                </button>
            @endif

            @if (! $readonly)
                <x-noerd::button
                    @click="$modal('{{ $listComponent }}', {id: {{ $modelId ?: 'null' }}, context: '{{ $this->selectionContext() }}', listActionMethod: 'selectAction'})"
                    class="!mt-0 !ml-1 !h-7 rounded-sm !px-2"
                    type="button"
                >
                    <x-noerd::icons.magnifying-glass></x-noerd::icons.magnifying-glass>
                </x-noerd::button>
            @endif
        </div>
    </div>
</div>
