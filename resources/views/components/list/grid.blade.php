{{-- Card-grid display mode, included from list/index.blade.php in place of the
     table when the list YAML sets `displayMode: grid`. Runs in the index scope,
     so $rows, $table, $listId, $listSettings, $multiSelect, $selectedRecordIds,
     $actions and $relations are available. Column filters and the sort control are
     offered as a bar above the cards (list/grid-controls) instead of the header
     funnels and sort headers. The remaining thead-only features (select-all, line
     numbers, summary) are not rendered in grid mode. --}}
@php
    // Tailwind cannot generate class names at runtime — every supported
    // gridColumns value maps to a literal class list so the scanner picks it up.
    $gridColumnClasses = [
        1 => 'grid-cols-1',
        2 => 'grid-cols-1 sm:grid-cols-2',
        3 => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
        4 => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4',
        5 => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5',
        6 => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6',
    ];
    $gridClass = $gridColumnClasses[(int) ($listSettings['gridColumns'] ?? 4)] ?? $gridColumnClasses[4];

@endphp

<div class="min-w-full pb-2 align-middle">
    {{-- Above the empty state too, so a filter that matches nothing stays clearable --}}
    @include('noerd::components.list.grid-controls')

    @if (count($rows))
        <div class="grid {{ $gridClass }} gap-3">
            @foreach ($rows as $key => $row)
                @php
                    // Card content from the YAML columns: the first column with a
                    // non-empty value renders as the bold card title, the remaining
                    // columns as secondary lines; empty values are skipped entirely.
                    $cells = [];
                    foreach ($table as $column) {
                        if (($column['field'] ?? null) === 'action') {
                            continue;
                        }
                        $value = \Noerd\Support\ListCellFormatter::scalar(data_get($row, $column['field'] ?? null));
                        $isBadge = ($column['type'] ?? 'text') === 'badge';
                        $text = $isBadge
                            ? \Noerd\Support\ListCellFormatter::badgeLabel($value, $column['options'] ?? [])
                            : \Noerd\Support\ListCellFormatter::format($value, $column);
                        if ($text === '') {
                            continue;
                        }
                        $cells[] = ['text' => $text, 'isBadge' => $isBadge];
                    }
                    $rowChecked = $multiSelect && in_array($this->normalizeRecordId($row['id'] ?? 0), $selectedRecordIds, true);
                @endphp
                <button
                    type="button"
                    wire:key="grid-{{ $listId }}-{{ $row['id'] ?? $key }}"
                    :class="{'ring-2 ring-brand-primary': selectedRow{{ $listId }} == {{ $key }} }"
                    @click="selectedRow{{ $listId }} = '{{ $key }}'"
                    wire:click="openListRow('{{ $row['id'] ?? '' }}')"
                    class="group hover:border-brand-primary relative flex cursor-pointer flex-col items-start gap-1 rounded-lg border bg-white p-4 text-left transition hover:shadow-sm {{ $rowChecked ? 'border-brand-primary' : 'border-gray-200' }}"
                >
                    @if ($multiSelect)
                        <div class="absolute top-3 right-3" @click.stop>
                            {{-- The checked state is part of the wire:key so the input is
                                 recreated when the selection clears — otherwise a user-toggled
                                 checkbox keeps its DOM checked state through the morph. --}}
                            <input
                                type="checkbox"
                                wire:key="grid-cb-{{ $listId }}-{{ $row['id'] ?? $key }}-{{ $rowChecked ? 1 : 0 }}"
                                wire:click.stop="toggleRecordSelection('{{ $row['id'] ?? '' }}')"
                                @checked($rowChecked)
                                class="text-brand-primary focus:ring-brand-border block h-4 w-4 cursor-pointer rounded border-gray-300"
                            />
                        </div>
                    @endif
                    @foreach ($cells as $index => $cell)
                        @if ($cell['isBadge'])
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-700">
                                {{ __($cell['text']) }}
                            </span>
                        @elseif ($index === 0)
                            <span class="group-hover:text-brand-primary font-medium text-gray-900">
                                {{ $cell['text'] }}
                            </span>
                        @else
                            <span class="text-sm text-gray-500">
                                {{ $cell['text'] }}
                            </span>
                        @endif
                    @endforeach
                    @if ($cells === [])
                        <span class="font-medium text-gray-400">&mdash;</span>
                    @endif
                </button>
            @endforeach
        </div>
    @else
        @php
            $primaryAction = $actions[0] ?? null;
        @endphp
        <div class="rounded-lg border border-dashed border-gray-300 px-6 py-12 text-center">
            <p class="text-sm text-gray-500">
                {{ __('No entries yet') }}
            </p>
            @if ($primaryAction)
                @php
                    // Mirrors the header action: route: opens a modal,
                    // action: calls a method on the list component.
                    $emptyStateRoute = $primaryAction['route'] ?? null;
                    $emptyStateRoute = $emptyStateRoute && \Illuminate\Support\Facades\Route::has($emptyStateRoute)
                        ? $emptyStateRoute
                        : null;
                @endphp
                <div class="mt-4 flex justify-center">
                    @if ($emptyStateRoute)
                        <x-noerd::button
                            variant="primary"
                            :icon="$primaryAction['heroicon'] ?? 'plus'"
                            class="h-8"
                            x-data
                            x-on:click.prevent="$modalRoute({{ Js::from($emptyStateRoute) }}, {{ Js::from($primaryAction['arguments'] ?? []) }})"
                        >
                            {{ __($primaryAction['label']) }}
                        </x-noerd::button>
                    @elseif (! empty($primaryAction['action']))
                        <x-noerd::button
                            variant="primary"
                            :icon="$primaryAction['heroicon'] ?? 'plus'"
                            class="h-8"
                            wire:click.prevent="{{ $primaryAction['action'] }}(null, {{ Js::from($relations ?? []) }})"
                        >
                            {{ __($primaryAction['label']) }}
                        </x-noerd::button>
                    @endif
                </div>
            @endif
        </div>
    @endif
</div>
