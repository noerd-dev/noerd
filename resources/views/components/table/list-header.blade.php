<x-slot:header>
    <x-noerd::modal-title>
        <div class="pb-3 lg:pb-0">
            {{$title}}
            @isset($rows)
                <span class="font-light">
                    ({{ $rows->total() }})
                </span>
            @endisset
        </div>

        @if($this->tableFilters())
            <div class="flex ml-4">
                @foreach($this->tableFilters() as $tableFilter)
                    <select wire:change="storeActiveListFilters"
                            wire:model.live="listFilters.{{$tableFilter['column']}}"
                            class="mr-4 min-w-36 rounded-md border border-dashed border-zinc-300 px-3 py-1.5 pr-8 text-sm focus:outline-none focus:ring-2 focus:ring-brand-border {{ !empty($listFilters[$tableFilter['column']]) ? '!border-brand-primary !border-solid' : '' }}">
                        <option value="">{{$tableFilter['label']}}</option>
                        @foreach($tableFilter['options'] ?? [] as $key => $option)
                            <option value="{{$key}}">{{$option}}</option>
                        @endforeach
                    </select>
                @endforeach
            </div>
        @endif

        @if(isset($disableSearch) && !$disableSearch)
            <div @if(!$newLabel)  :class="isModal ? 'mr-22' : ''" @endif class="ml-auto mr-2">
                <x-noerd::text-input
                    placeholder="{{ __('Search') }}" wire:model.live="search" type="text"
                    class="min-w-[200px] !mt-0 mb-3 lg:mb-0 h-[30px]"/>
            </div>
        @else
            <div class="ml-auto"></div>
        @endif
        @if($newLabel)
            <div :class="isModal ? 'mr-22' : ''">
                <x-noerd::buttons.primary class="!bg-brand-primary"
                                         style="height: 30px !important"
                                         wire:click.prevent="{{$action ?? 'listAction'}}(null, {{ Js::from($relations ?? []) }})">
                    <x-noerd::icons.plus class="text-white"/>
                    {{$newLabel}}
                </x-noerd::buttons.primary>
            </div>
        @endif
    </x-noerd::modal-title>
</x-slot:header>