{{-- Control bar for grid lists: the Excel funnels live in the table <thead> and the sort
     affordance in the header cells — grid mode renders neither. Included from
     list/grid.blade.php in the index scope, so $table, $listConfig, $notSortableColumns,
     $sortField, $sortAsc, $listId and $compact are available. Filter chips on the left
     (the very same table/column-filter popover, just labeled), the sort dropdown pushed
     to the right. Compact/embedded grid lists render no bar at all — they apply no column
     filters, and the table variant hides its header there too. --}}
@php
    $gridFilterableColumns = $compact ? [] : ($listConfig['filterableColumns'] ?? []);

    $gridSortColumns = $compact
        ? []
        : array_values(array_filter(
            $table,
            fn(array $gridColumn): bool => is_string($gridColumn['field'] ?? null)
                && $this->isSortableColumn($gridColumn['field'], $notSortableColumns),
        ));

    // Only labeled when the active sort is one of the YAML columns — a technical default
    // (`id`) has no column to name, the trigger then just reads "Sort by".
    $gridActiveSortColumn = null;
    foreach ($gridSortColumns as $gridSortCandidate) {
        if ($gridSortCandidate['field'] === $sortField) {
            $gridActiveSortColumn = $gridSortCandidate;
            break;
        }
    }
@endphp

@if ($gridFilterableColumns !== [] || $gridSortColumns !== [])
    <div class="mb-3 flex flex-wrap items-center gap-2">
        @foreach ($table as $gridFilterColumn)
            @php
                $gridFilterField = $gridFilterColumn['field'] ?? null;
            @endphp
            @if (is_string($gridFilterField) && $gridFilterField !== 'action' && in_array($gridFilterField, $gridFilterableColumns, true))
                @include('noerd::components.table.column-filter', [
                    'field' => $gridFilterField,
                    'align' => $gridFilterColumn['align'] ?? 'left',
                    'type' => $gridFilterColumn['type'] ?? 'text',
                    'options' => $gridFilterColumn['options'] ?? [],
                    'filterValue' => (string) ($listConfig['listColumnFilters'][$gridFilterField] ?? ''),
                    'filterLabel' => __($gridFilterColumn['label'] ?? $gridFilterField),
                ])
            @endif
        @endforeach

        @if ($gridSortColumns !== [])
            {{-- wire:key so the morph never pairs this Alpine dropdown with one of the keyed
                 filter chips beside it when the chip set changes (e.g. a list-view switch). --}}
            <div
                wire:key="grid-sort-{{ $listId }}"
                x-data="{ open: false }"
                @click.outside="open = false"
                class="relative ml-auto"
            >
                <button
                    type="button"
                    @click="open = ! open"
                    x-ref="gridSortBtn"
                    :aria-expanded="open"
                    aria-haspopup="true"
                    title="{{ __('Sort by') }}"
                    class="inline-flex items-center gap-1 rounded-md border border-gray-300 px-2 py-1 text-sm text-gray-600 hover:border-gray-400 hover:text-gray-900"
                >
                    @if ($gridActiveSortColumn)
                        <x-dynamic-component
                            :component="$sortAsc ? 'heroicons::outline.bars-arrow-up' : 'heroicons::outline.bars-arrow-down'"
                            class="size-3.5"
                        />
                        <span>{{ __('Sort by') }}: {{ __($gridActiveSortColumn['label'] ?? $gridActiveSortColumn['field']) }}</span>
                    @else
                        <x-dynamic-component component="heroicons::outline.arrows-up-down" class="size-3.5" />
                        <span>{{ __('Sort by') }}</span>
                    @endif
                </button>

                <div
                    x-show="open"
                    x-cloak
                    x-transition
                    @click.stop
                    @keydown.escape.stop="open = false"
                    x-anchor.bottom-end="$refs.gridSortBtn"
                    role="menu"
                    aria-orientation="vertical"
                    class="z-90 w-56 rounded-md bg-white py-1 text-left font-normal whitespace-normal shadow-lg ring-1 ring-black/5 focus:outline-hidden"
                >
                    @foreach ($gridSortColumns as $gridSortColumn)
                        <button
                            type="button"
                            role="menuitem"
                            wire:click="sortBy('{{ $gridSortColumn['field'] }}')"
                            @click="open = false"
                            class="flex w-full items-center justify-between gap-2 px-4 py-2 text-left text-sm hover:bg-gray-50 {{ $gridSortColumn['field'] === $sortField ? 'font-semibold text-gray-900' : 'font-normal text-gray-700' }}"
                        >
                            <span>{{ __($gridSortColumn['label'] ?? $gridSortColumn['field']) }}</span>
                            @if ($gridSortColumn['field'] === $sortField)
                                <x-dynamic-component
                                    :component="$sortAsc ? 'heroicons::outline.chevron-up' : 'heroicons::outline.chevron-down'"
                                    class="size-3.5"
                                />
                            @endif
                        </button>
                    @endforeach

                    <div class="my-1 border-t border-gray-100"></div>

                    @foreach ([[true, __('Ascending')], [false, __('Descending')]] as [$gridSortAsc, $gridSortDirectionLabel])
                        <button
                            type="button"
                            role="menuitem"
                            wire:click="setSortDirection({{ $gridSortAsc ? 'true' : 'false' }})"
                            @click="open = false"
                            class="block w-full px-4 py-2 text-left text-sm hover:bg-gray-50 {{ $sortAsc === $gridSortAsc ? 'font-semibold text-gray-900' : 'font-normal text-gray-700' }}"
                        >
                            {{ $gridSortDirectionLabel }}
                        </button>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endif
