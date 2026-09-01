<x-slot:header>
    {{-- The generic list header is ONE non-wrapping row at every viewport width
         (`row` on modal-title). From `xl` on, the filters, the search field, CSV
         export and the `style: secondary` actions sit inline on that row; below it
         they move into a drawer behind the funnel button instead of wrapping onto
         further lines, which is what makes the header usable on a phone. Only the
         title and the primary actions are on the row at every width.

         Everything is rendered ONCE — the controls container below IS the drawer
         panel below `xl` and the inline row from `xl` on, switched by `max-xl:` /
         `xl:` classes. Nothing is duplicated, so there is no key prefix, no
         `stacked` flag and no shortcut that could fire twice.

         The controls are included here rather than injected by modal-title
         (:listControls="false") because they have to sit inside this row. --}}
    <x-noerd::modal-title :listRelations="$relations ?? []" :listControls="false" row>
        @php
            $controls = $this->headerControls();
            $filterChips = $this->activeColumnFilterChips;
            $hasFilters = (bool) $this->tableFilters || $filterChips !== [];
            $hasDrawer = $hasFilters || $this->hasCollapsibleControls();

            // The badge counts everything the drawer hides from view, so an active
            // search is still visible while the search field itself is in there.
            $activeFilterCount = collect($this->listFilters)->filter()->count()
                + count($filterChips)
                + ($this->search !== '' ? 1 : 0);

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

        <div
            class="flex w-full min-w-0 items-center"
            @if ($hasDrawer)
                x-data="{ drawer: false }"
                @keydown.escape.window="drawer = false"
            @endif
        >
            <div class="flex min-w-0 shrink-0 items-center gap-2">
                <div class="min-w-0 truncate">
                    @if (count($listViews ?? []) > 1)
                        {{-- List-view switcher: pick one of several YAML views for this list --}}
                        <x-noerd::action-menu align="left" width="w-56" wrapperClass="relative">
                            <x-slot:trigger>
                                <button
                                    type="button"
                                    x-on:click="open = ! open"
                                    class="flex cursor-pointer items-center gap-1 rounded focus:outline-hidden"
                                    :aria-expanded="open"
                                    aria-haspopup="true"
                                    title="{{ __('Switch list view') }}"
                                >
                                    <span class="truncate">{{ $title }}</span>
                                    @if (isset($rows) && ! is_array($rows))
                                        <span class="font-light">({{ $rows->total() }})</span>
                                    @endif
                                    <x-noerd::icons.chevron-down class="my-auto text-gray-500" />
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
                        {{ $title }}
                        @if (isset($rows) && ! is_array($rows))
                            <span class="font-light"> ({{ $rows->total() }}) </span>
                        @endif
                    @endif
                </div>

                @if ($hasDrawer)
                    {{-- Opens the drawer. Only exists where the controls are in it. --}}
                    <button
                        type="button"
                        x-on:click="drawer = true"
                        class="relative inline-flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-sm border border-gray-300 text-gray-700 transition hover:bg-gray-100 focus:ring-2 focus:ring-offset-2 focus:outline-hidden xl:hidden"
                        :aria-expanded="drawer"
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

            @if ($hasDrawer)
                {{-- Backdrop, drawer widths only. Sits above the modal stack (z-50/z-[60])
                     so a list opened as a modal keeps its drawer usable. --}}
                <div
                    x-show="drawer"
                    x-cloak
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    x-on:click="drawer = false"
                    class="fixed inset-0 z-[70] bg-gray-800/50 xl:hidden"
                ></div>

                {{-- THE controls. One element, two layouts:
                     • from `xl`: a flex ROW inside the header, filters left, search and
                       buttons pushed right (`xl:ml-auto` on the search group).
                     • below `xl`: a full-height panel fixed to the right edge, stacked
                       as a flex COLUMN — search first, then the filters, then the
                       buttons, ordered with `order-*` rather than by rendering the
                       groups a second time.
                     Visibility below `xl` is driven by opacity/visibility (not
                     `display`), so the panel can transition without a translate that
                     would push the page into horizontal overflow. --}}
                <div
                    class="xl:ml-4 xl:flex xl:min-w-0 xl:flex-1 xl:flex-row xl:items-center xl:gap-1 xl:overflow-x-auto
                           max-xl:fixed max-xl:inset-y-0 max-xl:right-0 max-xl:z-[70] max-xl:flex max-xl:w-80 max-xl:max-w-[85vw] max-xl:flex-col max-xl:items-stretch max-xl:gap-3 max-xl:overflow-y-auto max-xl:bg-white max-xl:p-6 max-xl:text-sm max-xl:font-normal max-xl:shadow-xl max-xl:transition max-xl:duration-200"
                    x-bind:class="drawer
                        ? 'max-xl:visible max-xl:translate-x-0 max-xl:opacity-100'
                        : 'max-xl:invisible max-xl:translate-x-2 max-xl:opacity-0'"
                >
                    {{-- Drawer chrome — no place on the inline row. --}}
                    <div class="flex items-center border-b border-gray-300 pb-4 max-xl:order-first xl:hidden">
                        <span class="font-semibold text-slate-900">{{ __('Filters') }}</span>
                        <button
                            type="button"
                            x-on:click="drawer = false"
                            class="ml-auto inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-sm border border-gray-300 text-gray-700 transition hover:bg-gray-100 focus:ring-2 focus:ring-offset-2 focus:outline-hidden"
                            title="{{ __('Close') }}"
                        >
                            <span class="sr-only">{{ __('Close') }}</span>
                            <x-dynamic-component component="heroicons::mini.solid.x-mark" class="size-4" />
                        </button>
                    </div>

                    @if ($controls['search'])
                        <div class="max-xl:order-1 max-xl:w-full xl:order-2 xl:ml-auto xl:shrink-0 xl:pl-2">
                            @include('noerd::components.table.list-search', $controlArguments)
                        </div>
                    @endif

                    @if ($hasFilters)
                        <div class="flex max-xl:order-2 max-xl:flex-col max-xl:gap-3 xl:order-1 xl:min-w-0 xl:items-center xl:gap-1">
                            @include('noerd::components.table.list-filters', $controlArguments)
                        </div>
                    @endif

                    @if ($controls['csv'] || $controls['secondary'] !== [])
                        <div class="flex max-xl:order-3 max-xl:flex-col max-xl:gap-3 xl:order-3 xl:shrink-0 xl:items-center xl:gap-2 xl:pl-2">
                            @include('noerd::components.table.list-controls-secondary', $controlArguments)
                        </div>
                    @endif
                </div>
            @endif

            <div class="ml-auto flex shrink-0 items-center gap-4 pl-4" :class="isModal ? modalControlsClass : ''">
                @include('noerd::components.table.list-controls-primary', $controlArguments)
            </div>
        </div>
    </x-noerd::modal-title>
</x-slot:header>
