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
        <div class="grid grid-cols-12 gap-2">
            <div class="col-span-4">
                <select
                    wire:model.live="selectedRelationType"
                    class="focus:ring-brand-border block h-7 w-full appearance-none rounded-sm border border-zinc-200 bg-white py-1 ps-2 pe-2 text-base text-zinc-700 focus:ring-1 focus:outline-none disabled:text-zinc-500 sm:text-sm"
                    @if ($readonly) disabled @endif
                >
                    @foreach ($this->typeOptions as $typeKey => $typeLabel)
                        <option value="{{ $typeKey }}">{{ $typeLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-span-8">
                <div class="flex">
                    <input
                        class="focus:ring-brand-border block h-7 w-full cursor-pointer appearance-none rounded-sm border border-zinc-200 bg-white py-1 ps-2 pe-2 text-base text-zinc-700 placeholder-zinc-400 read-only:text-zinc-500 read-only:placeholder-zinc-400/70 focus:ring-1 focus:outline-none sm:text-sm"
                        type="text"
                        readonly
                        id="{{ $fieldName }}"
                        value="{{ $displayTitle }}"
                        @click="@if($displayTitle) $wire.openDetail() @elseif(! $readonly && $this->activeListComponent) $modal('{{ $this->activeListComponent }}', {id: null, context: '{{ $this->selectionContext() }}', listActionMethod: 'selectAction'}) @endif"
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

                    @if (! $readonly && $this->activeListComponent)
                        <x-noerd::button
                            @click="$modal('{{ $this->activeListComponent }}', {id: null, context: '{{ $this->selectionContext() }}', listActionMethod: 'selectAction'})"
                            class="!mt-0 !ml-1 !h-7 rounded-sm !px-2"
                            type="button"
                        >
                            <x-noerd::icons.magnifying-glass></x-noerd::icons.magnifying-glass>
                        </x-noerd::button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
