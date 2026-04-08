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
            <div class="flex items-center ml-4">
                @foreach($this->tableFilters() as $tableFilter)
                    <select wire:change="storeActiveListFilters"
                            wire:model.live="listFilters.{{$tableFilter['column']}}"
                            class="@if( ($this->listFilters[$tableFilter['column']] ?? '') !== '') !border-brand-primary !border-solid !border-2 @endif mr-4 min-w-36 max-w-48 truncate rounded-md border border-dashed border-zinc-300 px-3 py-1.5 pr-8 text-sm focus:outline-none focus:ring-2 focus:ring-brand-border">
                        <option value="">{{$tableFilter['label']}}</option>
                        @foreach($tableFilter['options'] ?? [] as $key => $option)
                            <option value="{{$key}}">{{$option}}</option>
                        @endforeach
                    </select>
                @endforeach
                @if(collect($this->listFilters)->filter()->isNotEmpty())
                    <button wire:click="clearAllListFilters" type="button"
                            class="ml-1 mt-0.5 whitespace-nowrap text-xs text-gray-400 hover:text-gray-600 transition-colors">
                        {{ __('noerd_clear_filters') }}
                    </button>
                @endif
            </div>
        @endif

        @php
            $searchShortcut = \Noerd\Helpers\KeyboardShortcutHelper::parse('search_focus', 's');
        @endphp

        @if(isset($disableSearch) && !$disableSearch)
            <div @if(empty($actions))  :class="isModal ? 'mr-22' : ''" @endif
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
        @if(!empty($actions))
            <div :class="isModal ? 'mr-22' : ''" class="flex gap-2">
                @foreach($actions as $actionIndex => $actionItem)
                    @php
                        $isSecondary = ($actionItem['style'] ?? '') === 'secondary';
                        $effectiveShortcut = $actionItem['shortcut']
                            ?? ($actionIndex === 0 ? 'n' : null);
                        $hasShortcut = $effectiveShortcut !== null;
                        $shortcut = $hasShortcut
                            ? \Noerd\Helpers\KeyboardShortcutHelper::parse('action_' . $actionItem['action'], $effectiveShortcut)
                            : null;
                    @endphp
                    @if($hasShortcut)
                        <div x-data @keydown.window="let e = $event; if ({{ $shortcut['js'] }}) { e.preventDefault(); $refs.actionBtn{{ $actionIndex }}.click(); }">
                    @else
                        <div>
                    @endif
                        @if($isSecondary)
                            <x-noerd::buttons.secondary
                                x-ref="actionBtn{{ $actionIndex }}"
                                style="height: 30px !important"
                                wire:click.prevent="{{ $actionItem['action'] }}(null, {{ Js::from($relations ?? []) }})">
                                @if(isset($actionItem['heroicon']))
                                    <x-dynamic-component :component="'heroicon-o-' . $actionItem['heroicon']" class="size-4" />
                                @endif
                                {{ __($actionItem['label']) }}
                                @if($hasShortcut)
                                    <kbd class="ml-2 rounded border border-gray-300 bg-gray-100 px-1 py-0.5 text-xs text-gray-500">{{ $shortcut['badge'] }}</kbd>
                                @endif
                            </x-noerd::buttons.secondary>
                        @else
                            <x-noerd::buttons.primary
                                x-ref="actionBtn{{ $actionIndex }}"
                                class="!bg-brand-primary relative"
                                style="height: 30px !important"
                                wire:click.prevent="{{ $actionItem['action'] }}(null, {{ Js::from($relations ?? []) }})">
                                @if(isset($actionItem['heroicon']))
                                    <x-dynamic-component :component="'heroicon-o-' . $actionItem['heroicon']" class="size-4 text-white" />
                                @else
                                    <x-noerd::icons.plus class="text-white"/>
                                @endif
                                {{ __($actionItem['label']) }}
                                @if($hasShortcut)
                                    <kbd class="ml-2 rounded border border-white/30 bg-white/20 px-1 py-0.5 text-xs text-white">{{ $shortcut['badge'] }}</kbd>
                                @endif
                            </x-noerd::buttons.primary>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-noerd::modal-title>
</x-slot:header>