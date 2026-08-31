<x-slot:header>
    {{-- The controls (search, CSV, registry list actions, YAML action buttons)
         are injected generically by modal-title for every NoerdList host —
         this file only contributes the title, count, view switcher and the
         filter chips. Relations are forwarded for YAML `action:` buttons. --}}
    <x-noerd::modal-title :listRelations="$relations ?? []">
        @php
            $activeColumnFilterChips = $this->activeColumnFilterChips;
            $hasListFilters = (bool) $this->tableFilters || $activeColumnFilterChips !== [];
            $hasClearAllFilters = collect($this->listFilters)->filter()->isNotEmpty() || count($activeColumnFilterChips) > 1;
            $activeFilterCount = collect($this->listFilters)->filter()->count() + count($activeColumnFilterChips);
        @endphp

        {{-- Title + filters share one flex track so the filters can claim the leftover width;
             the list controls injected by modal-title keep their ml-auto to the right of it. --}}
        <div
            class="min-w-0 lg:flex lg:flex-1 lg:items-center"
            @if ($hasListFilters)
                {{-- The filters never wrap onto a second line. `filterRow` is clipped to the
                     space it gets, so `scrollWidth > clientWidth` tells us the filters no longer
                     fit — the row then goes invisible (it keeps its box so it stays measurable)
                     and the funnel button next to the title opens them in a right-hand drawer.
                     Collapsing only ever frees width (the button is narrower than the row), so
                     the two states cannot oscillate. --}}
                x-data="{
                    collapsed: false,
                    drawerOpen: false,
                    measure() {
                        const row = this.$refs.filterRow;
                        if (! row) { return; }
                        this.collapsed = row.scrollWidth > row.clientWidth + 1;
                        if (! this.collapsed) { this.drawerOpen = false; }
                    },
                    init() {
                        this.$nextTick(() => this.measure());
                        new ResizeObserver(() => this.measure()).observe(this.$refs.filterRow);
                        new MutationObserver(() => this.measure()).observe(this.$refs.filterRow, {
                            childList: true,
                            subtree: true,
                            characterData: true,
                        });
                    },
                }"
            @endif
        >
            <div class="flex min-w-0 shrink-0 items-center gap-2 pb-3 lg:pb-0">
                <div class="min-w-0">
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

                @if ($hasListFilters)
                    {{-- Only rendered while the inline row cannot show the filters. --}}
                    <button
                        type="button"
                        x-show="collapsed"
                        x-cloak
                        x-on:click="drawerOpen = true"
                        class="relative inline-flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-sm border border-gray-300 text-gray-700 transition hover:bg-gray-100 focus:ring-2 focus:ring-offset-2 focus:outline-hidden"
                        :aria-expanded="drawerOpen"
                        title="{{ __('Filters') }}"
                    >
                        <span class="sr-only">{{ __('Filters') }}</span>
                        <x-dynamic-component component="heroicons::outline.funnel" class="size-4" />
                        @if ($activeFilterCount > 0)
                            <span class="absolute -top-1.5 -right-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-brand-primary px-1 text-[10px] leading-none font-semibold text-brand-primary-text">
                                {{ $activeFilterCount }}
                            </span>
                        @endif
                    </button>
                @endif
            </div>

            @if ($hasListFilters)
                <div
                    x-ref="filterRow"
                    class="ml-4 flex min-w-0 flex-1 items-center gap-1 overflow-hidden"
                    :class="collapsed ? 'invisible pointer-events-none' : ''"
                >
                    @include('noerd::components.table.list-filters', [
                        'tableFilters' => $this->tableFilters,
                        'listFilters' => $this->listFilters,
                        'chips' => $activeColumnFilterChips,
                        'hasClearAll' => $hasClearAllFilters,
                    ])
                </div>

                {{-- Filter drawer: the same controls, stacked, sliding in from the right. Sits
                     above the modal stack (z-50/z-[60]) so a list opened as a modal keeps it. --}}
                <div x-show="drawerOpen" x-cloak class="fixed inset-0 z-[70]" role="dialog" aria-modal="true">
                    <div
                        x-show="drawerOpen"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        x-on:click="drawerOpen = false"
                        class="absolute inset-0 bg-gray-800/50"
                    ></div>
                    <div
                        x-show="drawerOpen"
                        x-on:keydown.escape.window="drawerOpen = false"
                        x-transition:enter="transition transform ease-out duration-200"
                        x-transition:enter-start="translate-x-full"
                        x-transition:enter-end="translate-x-0"
                        x-transition:leave="transition transform ease-in duration-150"
                        x-transition:leave-start="translate-x-0"
                        x-transition:leave-end="translate-x-full"
                        class="absolute inset-y-0 right-0 flex w-80 max-w-full flex-col bg-white shadow-xl"
                    >
                        <div class="flex items-center border-b border-gray-300 px-6 py-6">
                            <span class="font-semibold text-slate-900">{{ __('Filters') }}</span>
                            <button
                                type="button"
                                x-on:click="drawerOpen = false"
                                class="ml-auto inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-sm border border-gray-300 text-gray-700 transition hover:bg-gray-100 focus:ring-2 focus:ring-offset-2 focus:outline-hidden"
                                title="{{ __('Close') }}"
                            >
                                <span class="sr-only">{{ __('Close') }}</span>
                                <x-dynamic-component component="heroicons::mini.solid.x-mark" class="size-4" />
                            </button>
                        </div>
                        <div class="flex flex-1 flex-col items-stretch gap-3 overflow-y-auto px-6 py-6 text-sm font-normal">
                            @include('noerd::components.table.list-filters', [
                                'tableFilters' => $this->tableFilters,
                                'listFilters' => $this->listFilters,
                                'chips' => $activeColumnFilterChips,
                                'hasClearAll' => $hasClearAllFilters,
                                'stacked' => true,
                                'keyPrefix' => 'drawer-',
                            ])
                        </div>
                    </div>
                </div>
            @endif
        </div>

    </x-noerd::modal-title>
</x-slot:header>
