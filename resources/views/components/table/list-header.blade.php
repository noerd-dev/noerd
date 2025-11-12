<x-slot:header>
    <x-noerd::modal-title class="flex items-center">
        <div>
            {{$title}}
            <span class="font-light">
                    ({{ $rows->total() }})
                </span>
        </div>

        @isset($filters)
            <div :class="isModal ? '' : 'ml-6'" class="flex my-auto">
                @foreach($filters as $key => $availableFilter)
                    <div class="-mt-6 mr-1">
                        <label class="break-keep text-xs">{{$availableFilter['title']}}</label>
                        <input wire:change="$refresh()" wire:model.live="currentTableFilter.{{ $key }}"
                               type="{{$availableFilter['type']}}"
                               class="disabled:opacity-50 border px-3 mr-1 block w-full py-1 rounded-md border-gray-300 shadow-xs focus:border-black focus:ring-black sm:text-sm {{ !empty($currentTableFilter[$key]) ? '!border-brand-highlight border !border-solid' : '' }}">
                    </div>
                @endforeach
            </div>
        @endisset

        @if($this->tableFilters())
            <div class="flex ml-4">
                @foreach($this->tableFilters() as $tableFilter)
                    <flux:select size="sm" wire:change="storeActiveTableFilters"
                                 wire:model.live="activeTableFilters.{{$tableFilter['column']}}"
                                 class="mr-4 border-dashed max-w-48 {{ !empty($activeTableFilters[$tableFilter['column']]) ? '!border-brand-highlight border !border-solid' : '' }}"
                                 placeholder="{{$tableFilter['label']}}">
                        @foreach($tableFilter['options'] ?? [] as $key => $option)
                            <flux:select.option value="{{$key}}">{{$option}}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endforeach
            </div>
        @endif

        @if(isset($disableSearch) && !$disableSearch)
            <div @if(!$newLabel)  :class="isModal ? 'mr-10' : ''" @endif class="ml-auto mr-2">
                <x-noerd::text-input
                    placeholder="{{ __('Search') }}" wire:model.live="search" type="text"
                    class="min-w-[200px] !mt-0 h-[30px]"/>
            </div>
        @else
            <div class="ml-auto"></div>
        @endif
        @if($newLabel)
            <div :class="isModal ? 'mr-10' : ''">
                <x-noerd::primary-button class="!bg-brand-primary"
                                         style="height: 30px !important"
                                         wire:click.prevent="{{$action ?? 'tableAction'}}(null, {{$relationId ?? null}})">
                    <x-noerd::icons.plus class="text-white"/>
                    {{$newLabel}}
                </x-noerd::primary-button>
            </div>
        @endif
    </x-noerd::modal-title>
</x-slot:header>