<x-slot:header>
    {{-- The controls (search, CSV, registry list actions, YAML action buttons)
         are injected generically by modal-title for every NoerdList host —
         this file only contributes the title, count, view switcher and the
         filter chips. Relations are forwarded for YAML `action:` buttons. --}}
    <x-noerd::modal-title :listRelations="$relations ?? []">
        <div class="pb-3 lg:pb-0">
            @if (count($listViews ?? []) > 1)
                {{-- List-view switcher: pick one of several YAML views for this list --}}
                <div x-data="{ open: false }" class="relative">
                    <button
                        type="button"
                        x-on:click="open = ! open"
                        x-on:click.outside="open = false"
                        class="flex items-center gap-1 rounded focus:outline-hidden"
                        :aria-expanded="open"
                        aria-haspopup="true"
                        title="{{ __('Switch list view') }}"
                    >
                        {{ $title }}
                        @if (isset($rows) && ! is_array($rows))
                            <span class="font-light">({{ $rows->total() }})</span>
                        @endif
                        <x-noerd::icons.chevron-down class="my-auto text-gray-500" />
                    </button>
                    <div
                        x-show="open"
                        x-transition
                        x-cloak
                        class="absolute left-0 z-90 mt-2 w-56 origin-top-left rounded-md bg-white py-1 shadow-lg ring-1 ring-black/5 focus:outline-hidden"
                        role="menu"
                        aria-orientation="vertical"
                    >
                        @foreach ($listViews as $viewKey => $view)
                            <button
                                type="button"
                                role="menuitem"
                                wire:click="switchListView('{{ $viewKey }}')"
                                x-on:click="open = false"
                                class="block w-full px-4 py-2 text-left text-sm {{ $viewKey === $activeListView ? 'font-semibold text-gray-900' : 'font-normal text-gray-700' }} hover:bg-gray-50"
                            >
                                {{ __($view['title']) }}
                                <span class="opacity-50">({{ $view['appLabel'] }})</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @else
                {{ $title }}
                @if (isset($rows) && ! is_array($rows))
                    <span class="font-light"> ({{ $rows->total() }}) </span>
                @endif
            @endif
        </div>

        @php
            $activeColumnFilterChips = $this->activeColumnFilterChips;
        @endphp
        @if ($this->tableFilters || $activeColumnFilterChips !== [])
            <div class="ml-4 flex flex-wrap items-center gap-1">
                @foreach ($this->tableFilters as $tableFilter)
                    @if (in_array($tableFilter['type'] ?? 'Picklist', ['ShowFrom', 'ShowUntil']))
                        <x-noerd::filters.date-dropdown
                            :filter="$tableFilter"
                            :value="$this->listFilters[$tableFilter['column']] ?? ''"
                        />
                    @else
                        <x-noerd::filters.picklist
                            :filter="$tableFilter"
                            :value="$this->listFilters[$tableFilter['column']] ?? ''"
                        />
                    @endif
                @endforeach
                @foreach ($activeColumnFilterChips as $filterChip)
                    <span
                        wire:key="column-filter-chip-{{ $filterChip['field'] }}"
                        class="flex items-center gap-1 rounded-full bg-gray-100 py-0.5 pr-1 pl-2.5 text-xs font-normal whitespace-nowrap text-gray-700"
                    >
                        <span class="font-medium">{{ $filterChip['label'] }}:</span>
                        {{ $filterChip['value'] }}
                        <button
                            type="button"
                            wire:click="clearColumnFilter('{{ $filterChip['field'] }}')"
                            title="{{ __('Clear filter') }}"
                            class="rounded-full p-0.5 text-gray-500 hover:bg-gray-200 hover:text-gray-700"
                        >
                            <x-dynamic-component component="heroicons::mini.solid.x-mark" class="size-3" />
                        </button>
                    </span>
                @endforeach
                @if (collect($this->listFilters)->filter()->isNotEmpty() || count($activeColumnFilterChips) > 1)
                    <x-noerd::button
                        variant="icon"
                        size="sm"
                        icon="x-mark"
                        type="button"
                        wire:click="clearAllListFilters"
                        :title="__('Clear all filters')"
                    />
                @endif
            </div>
        @endif

    </x-noerd::modal-title>
</x-slot:header>
