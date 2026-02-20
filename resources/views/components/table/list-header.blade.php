<x-slot:header>
    <x-noerd::modal-title>
        <div class="pb-3 lg:pb-0">
            {{$title}}
            @if(isset($rows) && ! is_array($rows))
                <span class="font-light">
                    ({{ $rows->total() }})
                </span>
            @endif
        </div>

        @if($this->tableFilters())
            <div class="flex ml-4">
                @foreach($this->tableFilters() as $tableFilter)
                    <select wire:change="storeActiveListFilters"
                            wire:model.live="listFilters.{{$tableFilter['column']}}"
                            class="@if( ($this->listFilters[$tableFilter['column']] ?? '') !== '') !border-brand-primary !border-solid !border-2 @endif mr-4 min-w-36 rounded-md border border-dashed border-zinc-300 px-3 py-1.5 pr-8 text-sm focus:outline-none focus:ring-2 focus:ring-brand-border">
                        <option value="">{{$tableFilter['label']}}</option>
                        @foreach($tableFilter['options'] ?? [] as $key => $option)
                            <option value="{{$key}}">{{$option}}</option>
                        @endforeach
                    </select>
                @endforeach
            </div>
        @endif

        @php
            $searchShortcut = \Noerd\Helpers\KeyboardShortcutHelper::parse('search_focus', '/');
            $newEntryShortcut = \Noerd\Helpers\KeyboardShortcutHelper::parse('new_entry', 'alt+n');
        @endphp

        @if(isset($disableSearch) && !$disableSearch)
            <div @if(!$newLabel)  :class="isModal ? 'mr-22' : ''" @endif
                 class="ml-auto mr-2"
                 x-data="{ searchFocused: false }"
                 @keydown.window="let e = $event; if ({{ $searchShortcut['js'] }}) { e.preventDefault(); $refs.searchInput.focus(); }">
                <div class="relative">
                    <x-noerd::text-input
                        x-ref="searchInput"
                        @focus="searchFocused = true"
                        @blur="searchFocused = false"
                        @keydown.escape="$refs.searchInput.blur()"
                        placeholder="{{ __('Search') }}" wire:model.live="search" type="text"
                        class="min-w-[200px] !mt-0 mb-3 lg:mb-0 h-[30px] pr-8"/>
                    <kbd x-show="!searchFocused"
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         class="pointer-events-none absolute right-1.5 top-1/2 -translate-y-1/2 rounded border border-gray-300 bg-gray-100 px-1.5 py-0.5 text-xs text-gray-500">{{ $searchShortcut['badge'] }}</kbd>
                </div>
            </div>
        @else
            <div class="ml-auto"></div>
        @endif
        @if($newLabel)
            <div :class="isModal ? 'mr-22' : ''"
                 x-data
                 @keydown.window="let e = $event; if ({{ $newEntryShortcut['js'] }}) { e.preventDefault(); $refs.newEntryBtn.click(); }">
                <x-noerd::buttons.primary x-ref="newEntryBtn"
                                         class="!bg-brand-primary relative"
                                         style="height: 30px !important"
                                         wire:click.prevent="{{$action ?? 'listAction'}}(null, {{ Js::from($relations ?? []) }})">
                    <x-noerd::icons.plus class="text-white"/>
                    {{$newLabel}}
                    <kbd class="ml-2 rounded border border-white/30 bg-white/20 px-1 py-0.5 text-xs text-white">{{ $newEntryShortcut['badge'] }}</kbd>
                </x-noerd::buttons.primary>
            </div>
        @endif
    </x-noerd::modal-title>
</x-slot:header>