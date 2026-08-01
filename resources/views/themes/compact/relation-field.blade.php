<div class="flex items-center gap-2">
    <x-noerd::input-label for="{{ $fieldName }}" :value="__($label)" :required="$required" :title="__($label)" class="!pb-0 w-36 shrink-0 truncate"/>
    <div class="flex-1 min-w-0">
        <div class="flex">
            <input
                class="w-full cursor-pointer border border-zinc-200 rounded-sm block appearance-none text-base sm:text-sm py-1 h-7 ps-2 pe-2 bg-white text-zinc-700 read-only:text-zinc-500 placeholder-zinc-400 read-only:placeholder-zinc-400/70 focus:outline-none focus:ring-1 focus:ring-brand-border"
                type="text"
                readonly
                id="{{ $fieldName }}"
                value="{{ $displayTitle }}"
                @click="@if($displayTitle) $wire.openDetail() @elseif(! $readonly) $modal('{{ $listComponent }}', {id: {{ $modelId ?: 'null' }}, context: '{{ $fieldName }}', listActionMethod: 'selectAction'}) @endif"
            >

            @if($displayTitle && ! $readonly)
                <button
                    wire:click="clear"
                    class="h-7 inline-flex items-center px-2 !mt-0 !ml-1 text-zinc-400 hover:text-zinc-600"
                    type="button"
                >
                    <x-noerd::icons.x-mark class="w-5 h-5"></x-noerd::icons.x-mark>
                </button>
            @endif

            @if(! $readonly)
                <x-noerd::button
                    @click="$modal('{{ $listComponent }}', {id: {{ $modelId ?: 'null' }}, context: '{{ $fieldName }}', listActionMethod: 'selectAction'})"
                    class="!h-7 !px-2 rounded-sm !mt-0 !ml-1"
                    type="button"
                >
                    <x-noerd::icons.magnifying-glass></x-noerd::icons.magnifying-glass>
                </x-noerd::button>
            @endif
        </div>
    </div>
</div>
