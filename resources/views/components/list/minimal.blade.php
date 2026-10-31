@php
    // Self-contained, like list/index.blade.php: the config comes from the
    // component's listData() — the trait's query for the slim syntax, the
    // component's own rows when it overrides the method — memoized per request.
    $listConfig = $this->builtListConfig();

    $listId = $listConfig['listId'] ?? '';
    $rows = $listConfig['rows'] ?? [];
    $listSettings = $listConfig['listSettings'] ?? [];
    $allColumns = $listSettings['columns'] ?? [];

    // Restrict to the explicitly requested columns, preserving the declared order.
    // Fall back to all columns when none are specified.
    $minimalColumns = $this->minimalColumns ?? [];
    $table = ! empty($minimalColumns)
        ? collect($minimalColumns)
            ->map(fn($field) => collect($allColumns)->firstWhere('field', $field))
            ->filter()
            ->values()
            ->all()
        : $allColumns;

    $hasMore = $rows instanceof \Illuminate\Pagination\LengthAwarePaginator && $rows->total() > $rows->count();

    // Tailwind cannot generate class names at runtime — map the YAML `align`
    // value to a literal class so the scanner picks it up.
    $alignClasses = ['left' => 'text-left', 'center' => 'text-center', 'right' => 'text-right'];
@endphp

{{-- Break out of the embedding page's p-6 so the table sits flush to the card edges --}}
<div class="-mx-6">
    @if ($listConfig['objectAccessDenied'] ?? false)
        @include('noerd::components.object-access-denied')
    @else
        <table class="min-w-full border-separate border-spacing-0">
            <thead>
                <tr>
                    @foreach ($table as $column)
                        <th
                            scope="col"
                            class="{{ $alignClasses[$column['align'] ?? 'left'] ?? 'text-left' }} border-b border-gray-300 bg-brand-navi/75 py-1 px-2 first:pl-4 last:pr-4 text-[11px] font-medium whitespace-nowrap text-gray-600"
                        >
                            {{ __($column['label'] ?? '') }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $key => $row)
                    <tr
                        wire:key="minimal-{{ $listId }}-{{ $key }}"
                        wire:click="findListAction({{ \Illuminate\Support\Js::from((string) $key) }})"
                        class="hover:bg-brand-bg cursor-pointer"
                    >
                        @foreach ($table as $column)
                            <td class="{{ $alignClasses[$column['align'] ?? 'left'] ?? 'text-left' }} border-b border-gray-100 py-1 px-2 first:pl-4 last:pr-4 text-xs whitespace-nowrap text-gray-700">
                                @php
                                    $cellValue = data_get($row, $column['field'] ?? null);
                                    $isBadge = in_array($column['type'] ?? 'text', ['badge', 'relationBadge'], true);
                                    $cellText = \Noerd\Support\ListCellFormatter::format($cellValue, $column);
                                @endphp
                                @if ($minimalRendererView = \Noerd\Support\ListCellRenderer::viewFor($column))
                                    @include($minimalRendererView, \Noerd\Support\ListCellRenderer::data($cellValue, $row, $column))
                                @elseif (($column['type'] ?? 'text') === 'preview')
                                    @php
                                        $minimalPreview = \Noerd\Support\ListCellFormatter::preview($cellValue);
                                    @endphp
                                    @if ($minimalPreview)
                                        @include('noerd::components.list.preview', ['preview' => $minimalPreview, 'previewSize' => 'cell'])
                                    @endif
                                @elseif ($isBadge)
                                    @if ($cellText !== '')
                                        <x-noerd::badge size="xs">{{ $cellText }}</x-noerd::badge>
                                    @endif
                                @else
                                    {{ $cellText }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td
                            colspan="{{ max(count($table), 1) }}"
                            class="border-b border-gray-100 px-4 py-4 text-center"
                        >
                            <p class="text-xs text-gray-500">{{ __('No entries yet') }}</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @php
            $showMoreRoute = ($this->showMoreRoute ?? null);
            $showMoreRoute = $showMoreRoute && \Illuminate\Support\Facades\Route::has($showMoreRoute) ? $showMoreRoute : null;
        @endphp
        @if ($hasMore && ($showMoreRoute || $this->showMoreComponent))
            <div class="border-t border-gray-200 px-4 py-1.5">
                <button
                    type="button"
                    {{-- The full list is narrowed by showMoreArguments, which a plain list
                     route cannot express — resolve the component, keep the URL. --}}
                    @if ($showMoreRoute)
                        @click="$modalRoute({{ \Illuminate\Support\Js::from($showMoreRoute) }}, {{ \Illuminate\Support\Js::from($this->showMoreArguments) }}, null, null, null, {rewriteUrl: false, fallbackComponent: {{ \Illuminate\Support\Js::from($this->showMoreComponent) }}})"
                    @else
                        @click="$modal({{ \Illuminate\Support\Js::from($this->showMoreComponent) }}, {{ \Illuminate\Support\Js::from($this->showMoreArguments) }})"
                    @endif
                    class="text-brand-primary text-xs font-medium hover:underline"
                >
                    {{ __('Show more') }}
                </button>
            </div>
        @endif
    @endif
</div>
