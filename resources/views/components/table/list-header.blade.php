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
                    @if(in_array($tableFilter['type'] ?? 'Picklist', ['ShowFrom', 'ShowUntil']))
                        <x-noerd::filters.date-dropdown :filter="$tableFilter" :value="$this->listFilters[$tableFilter['column']] ?? ''" />
                    @else
                        <x-noerd::filters.picklist :filter="$tableFilter" :value="$this->listFilters[$tableFilter['column']] ?? ''" />
                    @endif
                @endforeach
                @if(collect($this->listFilters)->filter()->isNotEmpty())
                    <button wire:click="clearAllListFilters" type="button"
                            class="ml-1 mt-0.5 whitespace-nowrap text-xs text-gray-400 hover:text-gray-600 transition-colors">
                        {{ __('Clear all filters') }}
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
                        class="min-w-[200px] !mt-0 mb-3 lg:mb-0 h-8 pr-8"/>
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
                        <x-noerd::button
                            :variant="$isSecondary ? 'secondary' : 'primary'"
                            :icon="$actionItem['heroicon'] ?? ($isSecondary ? null : 'plus')"
                            x-ref="actionBtn{{ $actionIndex }}"
                            class="relative h-8"
                            wire:click.prevent="{{ $actionItem['action'] }}(null, {{ Js::from($relations ?? []) }})">
                            {{ __($actionItem['label']) }}
                            @if($hasShortcut)
                                <kbd @class([
                                    'ml-2 rounded px-1 py-0.5 text-xs',
                                    'border border-gray-300 bg-gray-100 text-gray-500' => $isSecondary,
                                    'border border-white/30 bg-white/20 text-brand-primary-text' => !$isSecondary,
                                ])>{{ $shortcut['badge'] }}</kbd>
                            @endif
                        </x-noerd::button>
                    </div>
                @endforeach
            </div>
        @endif
    </x-noerd::modal-title>
</x-slot:header>