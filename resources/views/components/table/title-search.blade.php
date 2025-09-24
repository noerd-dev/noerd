{{--
<div class="flex w-full">
    <div class="my-auto shrink-0">
        @if($disableModal)
        <div :class="isModal ? 'hidden' : ''">
            <x-noerd::title>
                {{ __($title) }}
            </x-noerd::title>
        </div>
        @endif
    </div>

    @isset($states)
    <flux:select wire:model.live="filter" class="ml-4">
        @foreach($states as $state)
        <flux:select.option value="{{$state['state']}}">{{$state['title']}}</flux:select.option>
        @endforeach
    </flux:select>
    @endisset

    <div class="flex ml-auto shrink-0">

        @isset($filters)
        <div :class="isModal ? '' : 'ml-6'" class="flex my-auto">
            @foreach($filters as $key => $availableFilter)
            <div class="-mt-6 mr-1">
                <label class="break-keep text-xs">{{$availableFilter['title']}}</label>
                <input wire:change="$refresh()" wire:model.live="currentTableFilter.{{ $key }}"
                       type="{{$availableFilter['type']}}"
                       class=" disabled:opacity-50 border px-3 mr-1 block w-full py-1 rounded-md border-gray-300 shadow-xs focus:border-black focus:ring-black sm:text-sm {{ !empty($currentTableFilter[$key]) ? '!border-brand-highlight border !border-solid' : '' }}">
            </div>
            @endforeach
        </div>
        @endisset
        @if(isset($disableSearch) && !$disableSearch)
        <div class="ml-auto my-auto">
            <x-noerd::text-input id="start-field" autofocus="autofocus"
                                 placeholder="{{ __('Search') }}" wire:model.live="search" type="text"
                                 class="min-w-[200px] mx-3 block w-full mt-0!"/>
        </div>
        @endif
        @if($newLabel)
        <div class="ml-4 my-auto">
            <x-noerd::primary-button class="!bg-brand-primary"
                                     wire:click.prevent="{{$action ?? 'tableAction'}}(null, {{$relationId ?? null}})">
                <x-noerd::icons.plus class="text-white"/>
                {{ __($newLabel) }}
            </x-noerd::primary-button>
        </div>
        @endif
    </div>
</div>

<div class="flex">
    @isset($tableFilters)
        @foreach($tableFilters as $tableFilter)
            <flux:select size="sm" wire:change="storeActiveTableFilters"
                         wire:model.live="activeTableFilters.{{$tableFilter['column']}}"
                         class="mr-4 border-dashed mt-8 max-w-48 {{ !empty($activeTableFilters[$tableFilter['column']]) ? '!border-brand-highlight border !border-solid' : '' }}"
                         placeholder="{{$tableFilter['label']}}">
                @foreach($tableFilter['options'] ?? [] as $key => $option)
                    <flux:select.option value="{{$key}}">{{$option}}</flux:select.option>
                @endforeach
            </flux:select>
        @endforeach
    @endisset
</div>
--}}
@if($description)
    <div class="text-sm w-full text-gray-700 py-6 pr-36">
        {{ __($description) }}
    </div>
@endif
