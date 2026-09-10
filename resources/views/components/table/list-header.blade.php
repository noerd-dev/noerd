<x-slot:header>
    {{-- The generic list header is TWO rows at every viewport width:

         • The TITLE row (modal-title, `row` = one non-wrapping flex line): the
           list title with its record count (and the view switcher when the list
           ships several YAML views) on the left, every button on the right — the
           `style: secondary` actions, CSV export and the primary "New …" actions.
           modal-title draws the grey separator below it.
         • The FILTER row, rendered only when something belongs in it (a search
           field, header filters/chips, or registry list actions): the search field
           on the left, the filters in a horizontally SCROLLING strip next to it,
           and on the right the registry list actions (e.g. a module's layout
           and object managers) followed by the pagination summary with its
           previous/next buttons.

         Neither row ever wraps and nothing collapses into a drawer: the three
         zones of the filter row are `shrink-0` | `flex-1 min-w-0` | `shrink-0`,
         so any overflow goes into the middle strip, which scrolls exactly like
         the quick-menu. No JavaScript measures or positions anything —
         `noerdScrollShadow` only toggles the idle scrollbar class.

         Both rows live in the page's header slot, i.e. above the scrolling body,
         so they stay put while the table scrolls.

         The controls are included here rather than injected by modal-title
         (:listControls="false") because they are spread over the two rows. --}}
    @php
        $controls = $this->headerControls();
        $filterChips = $this->activeColumnFilterChips;
        $hasFilters = (bool) $this->tableFilters || $filterChips !== [];
        $paginator = isset($rows) && ! is_array($rows) ? $rows : null;

        // The filter row exists only when something belongs in it. Pagination
        // alone never opens it — the footer already carries the same navigation.
        $hasFilterRow = $controls['search'] || $hasFilters || $controls['registry'] !== [];

        $controlArguments = [
            'host' => $this,
            'controls' => $controls,
            'listRelations' => $relations ?? [],
            'tableFilters' => $this->tableFilters,
            'listFilters' => $this->listFilters,
            'chips' => $filterChips,
            'hasClearAll' => collect($this->listFilters)->filter()->isNotEmpty() || count($filterChips) > 1,
        ];
    @endphp

    <x-noerd::modal-title :listRelations="$relations ?? []" :listControls="false" row>
        <div class="flex w-full min-w-0 items-center">
            {{-- The title must never clip its own overflow: the view switcher's
                 dropdown is an absolutely positioned child of it, and an
                 `overflow: hidden` here would swallow the whole panel. Only the
                 title TEXT truncates, one level further in. --}}
            <div class="min-w-0">
                @if (count($listViews ?? []) > 1)
                    {{-- List-view switcher: pick one of several YAML views for this list --}}
                    <x-noerd::action-menu align="left" width="w-56" wrapperClass="relative min-w-0">
                        <x-slot:trigger>
                            <button
                                type="button"
                                x-on:click="open = ! open"
                                class="flex min-w-0 max-w-full cursor-pointer items-center gap-1 rounded focus:outline-hidden"
                                :aria-expanded="open"
                                aria-haspopup="true"
                                title="{{ __('Switch list view') }}"
                            >
                                <span class="truncate">{{ $title }}</span>
                                @if ($paginator !== null)
                                    <span class="shrink-0 font-light">({{ $paginator->total() }})</span>
                                @endif
                                <x-noerd::icons.chevron-down class="my-auto shrink-0 text-gray-500" />
                            </button>
                        </x-slot:trigger>

                        @foreach ($listViews as $viewKey => $view)
                            <x-noerd::action-menu-item
                                wire:click="switchListView('{{ $viewKey }}')"
                                :active="$viewKey === $activeListView"
                            >
                                {{ __($view['title']) }}
                                <span class="opacity-50">({{ $view['appLabel'] }})</span>
                            </x-noerd::action-menu-item>
                        @endforeach
                    </x-noerd::action-menu>
                @else
                    <div class="truncate">
                        {{ $title }}
                        @if ($paginator !== null)
                            <span class="font-light"> ({{ $paginator->total() }}) </span>
                        @endif
                    </div>
                @endif
            </div>

            <div class="ml-auto flex shrink-0 items-center gap-2 pl-4" :class="isModal ? modalControlsClass : ''">
                @include('noerd::components.table.list-controls-secondary', $controlArguments)
                @include('noerd::components.table.list-controls-primary', $controlArguments)
            </div>
        </div>
    </x-noerd::modal-title>

    @if ($hasFilterRow)
        <div
            wire:key="list-filter-row"
            class="flex w-full min-w-0 items-center gap-x-2 border-b border-gray-300 px-6 py-2 text-sm font-normal"
        >
            @if ($controls['search'])
                <div class="shrink-0">
                    @include('noerd::components.table.list-search', $controlArguments)
                </div>
            @endif

            @if ($hasFilters)
                {{-- The scrolling strip, built like the quick-menu: overflow-x-scroll
                     (not auto) keeps the 6px scrollbar track permanently reserved and
                     -mb-[6px] cancels it out of the layout, so the controls never
                     shift when the scrollbar appears — it draws in the row's bottom
                     padding instead. noerd-scrollbar-idle hides the thumb while
                     nothing overflows (a custom WebKit scrollbar would otherwise draw
                     it at full length); noerdScrollShadow keeps the class in sync.

                     A scroll container clips BOTH axes, so `p-1` opens 4px on every
                     side for the controls' focus rings. Popovers are safe: the
                     picklist is a native <select>, the date dropdown anchors with
                     `x-anchor.fixed`, and chips have none. --}}
                <div
                    class="noerd-scrollbar noerd-scrollbar-idle -mb-[6px] flex min-w-0 flex-1 items-center gap-x-2 overflow-x-scroll p-1"
                    x-data="noerdScrollShadow()"
                >
                    @include('noerd::components.table.list-filters', $controlArguments)
                </div>
            @endif

            <div class="ml-auto flex shrink-0 items-center gap-2 pl-2">
                @include('noerd::components.table.list-controls-registry', $controlArguments)
                @if ($paginator !== null && $paginator->total() > 0)
                    @include('noerd::components.table.list-pagination-nav', ['paginator' => $paginator])
                @endif
            </div>
        </div>
    @endif
</x-slot:header>
