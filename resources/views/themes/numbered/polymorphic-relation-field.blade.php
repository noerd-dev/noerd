<x-noerd::detail.numbered-row :field="$this->numberedRowField()">
    <div class="grid grid-cols-12 gap-2">
        <div class="col-span-4">
            <select
                wire:model.live="selectedRelationType"
                class="w-full block appearance-none rounded-none border border-zinc-400 bg-white py-1.5 ps-2 pe-2 h-9 text-base sm:text-sm text-zinc-700 disabled:text-zinc-500 focus:border-dotted focus:border-zinc-600 focus:ring-0 focus:outline-none"
                @if($readonly) disabled @endif
            >
                @foreach($this->typeOptions as $typeKey => $typeLabel)
                    <option value="{{ $typeKey }}">{{ $typeLabel }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-span-8">
            <div class="flex">
                <input
                    class="w-full cursor-pointer block appearance-none rounded-none border border-zinc-400 bg-white py-1.5 ps-2 pe-2 h-9 text-base sm:text-sm text-zinc-700 read-only:text-zinc-500 placeholder-zinc-400 read-only:placeholder-zinc-400/70 focus:border-dotted focus:border-zinc-600 focus:ring-0 focus:outline-none"
                    type="text"
                    readonly
                    id="{{ $fieldName }}"
                    value="{{ $displayTitle }}"
                    @click="@if($displayTitle) $wire.openDetail() @elseif(! $readonly && $this->activeListComponent) $modal('{{ $this->activeListComponent }}', {id: null, context: '{{ $fieldName }}', listActionMethod: 'selectAction'}) @endif"
                >

                @if($displayTitle && ! $readonly)
                    <button
                        wire:click="clear"
                        class="h-9 inline-flex items-center px-2 !mt-0 !ml-1 text-zinc-400 hover:text-zinc-600"
                        type="button"
                    >
                        <x-noerd::icons.x-mark class="w-5 h-5"></x-noerd::icons.x-mark>
                    </button>
                @endif

                @if(! $readonly && $this->activeListComponent)
                    <x-noerd::button
                        @click="$modal('{{ $this->activeListComponent }}', {id: null, context: '{{ $fieldName }}', listActionMethod: 'selectAction'})"
                        class="!h-9 !px-2 rounded-none !mt-0 !ml-1"
                        type="button"
                    >
                        <x-noerd::icons.magnifying-glass></x-noerd::icons.magnifying-glass>
                    </x-noerd::button>
                @endif
            </div>
        </div>
    </div>
</x-noerd::detail.numbered-row>
