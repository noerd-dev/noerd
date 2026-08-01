<div>
    <x-noerd::input-label for="{{ $fieldName }}" :value="__($label)" :required="$required"/>
    <div class="grid grid-cols-12 gap-2">
        <div class="col-span-4">
            <select
                wire:model.live="selectedRelationType"
                class="w-full border rounded-lg block disabled:shadow-none appearance-none text-base sm:text-sm py-2 h-8 leading-[1.375rem] ps-3 pe-3 bg-white text-zinc-700 disabled:text-zinc-500 shadow-xs border-zinc-200 border-b-zinc-300/80 disabled:border-b-zinc-200 focus:outline-none focus:ring-2 focus:ring-brand-border focus:ring-offset-2"
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
                    class="w-full cursor-pointer border rounded-lg block read-only:shadow-none appearance-none text-base sm:text-sm py-2 h-8 leading-[1.375rem] ps-3 pe-3 bg-white text-zinc-700 read-only:text-zinc-500 placeholder-zinc-400 read-only:placeholder-zinc-400/70 shadow-xs border-zinc-200 border-b-zinc-300/80 read-only:border-b-zinc-200 focus:outline-none focus:ring-2 focus:ring-brand-border focus:ring-offset-2"
                    type="text"
                    readonly
                    id="{{ $fieldName }}"
                    value="{{ $displayTitle }}"
                    @click="@if($displayTitle) $wire.openDetail() @elseif(! $readonly && $this->activeListComponent) $modal('{{ $this->activeListComponent }}', {id: null, context: '{{ $fieldName }}', listActionMethod: 'selectAction'}) @endif"
                >

                @if($displayTitle && ! $readonly)
                    <button
                        wire:click="clear"
                        class="h-8 inline-flex items-center px-2 !mt-0 !ml-1 text-zinc-400 hover:text-zinc-600"
                        type="button"
                    >
                        <x-noerd::icons.x-mark class="w-5 h-5"></x-noerd::icons.x-mark>
                    </button>
                @endif

                @if(! $readonly && $this->activeListComponent)
                    <x-noerd::button
                        @click="$modal('{{ $this->activeListComponent }}', {id: null, context: '{{ $fieldName }}', listActionMethod: 'selectAction'})"
                        class="h-8 rounded !mt-0 !ml-1"
                        type="button"
                    >
                        <x-noerd::icons.magnifying-glass></x-noerd::icons.magnifying-glass>
                    </x-noerd::button>
                @endif
            </div>
        </div>
    </div>
</div>
