@props([
    'listConfig' => null,
    'relations' => [],
    'summary' => null,
    'compact' => null,
    'minimal' => null,
])

@php
    // Minimal mode renders a slim, column-restricted widget variant (see list.minimal)
    $minimal = $minimal ?? ($this->minimal ?? false);
@endphp

@if($minimal)
    @include('noerd::components.list.minimal')
@else
@php
    // Auto-fetch listConfig from parent Livewire component if not provided
    $listConfig = $listConfig ?? $this->with()['listConfig'] ?? [];

    // Compact mode hides the list header and the pagination footer (e.g. for embedded lists)
    $compact = $compact ?? ($this->compact ?? false);

    // Extract values from listConfig
    $listId = $listConfig['listId'] ?? '';
    $sortField = $listConfig['sortField'] ?? 'id';
    $sortAsc = $listConfig['sortAsc'] ?? false;
    $rows = $listConfig['rows'] ?? [];
    $notSortableColumns = $listConfig['notSortableColumns'] ?? [];
    $listSettings = $listConfig['listSettings'] ?? [];

    // Extract values from listSettings
    $title = __($listSettings['title'] ?? '');
    $actions = $listSettings['actions'] ?? [];
    $disableSearch = $listSettings['disableSearch'] ?? false;
    $description = $listSettings['description'] ?? false;
    $showSummary = $listSettings['showSummary'] ?? true;
    $table = $listSettings['columns'] ?? [];

    $listAction = $this->listActionMethod ?? 'listAction';

    // Multi-select: a leading checkbox column plus a footer that is either a picker
    // confirm bar (returnsSelection) or a bulk-action bar (YAML `bulkActions`).
    // Enabled by the `returnsSelection`/`multiSelect` props (picker) or a top-level
    // `multiSelect: true` in the list YAML (a normal bulk-action page). Always off in
    // compact/embedded lists.
    $returnsSelection = $this->returnsSelection ?? false;
    $bulkActions = $listSettings['bulkActions'] ?? [];
    $multiSelect = ! $compact && (($this->multiSelect ?? false) || $returnsSelection || ($listSettings['multiSelect'] ?? false));
    $selectedRecordIds = $multiSelect ? ($this->selectedRecordIds ?? []) : [];

    // Excel-style row numbers: a leading number column that restarts at 1 per page
    // ($loop->iteration over the current page's rows). Enabled via the list YAML.
    $showLineNumbers = ($this->showLineNumbers ?? false) || ($listSettings['showLineNumbers'] ?? false);
    $visibleRowIds = [];
    $allVisibleSelected = false;
    if ($multiSelect) {
        foreach ($rows as $multiSelectRow) {
            $visibleRowIds[] = (int) ($multiSelectRow['id'] ?? 0);
        }
        $visibleRowIds = array_values(array_filter($visibleRowIds));
        $allVisibleSelected = $visibleRowIds !== [] && array_diff($visibleRowIds, $selectedRecordIds) === [];
    }
@endphp

<div>
    @unless($compact)
        @include('noerd::components.table.list-header')
    @endunless

    <div x-data="{
        selectedRow{{$listId}}: 0,
        isInsideModal: false,
        isInBlockingField() {
            const el = document.activeElement;
            return ['INPUT', 'TEXTAREA', 'SELECT'].includes(el?.tagName)
                || el?.isContentEditable
                || !!el?.closest?.('[contenteditable]');
        },
        canHandleListKey() {
            return ($store.app.currentId == '{{$listId}}')
                && (this.isInsideModal || !$store.app.modalOpen)
                && !this.isInBlockingField();
        },
    }"
         x-init="
        $store.app.setId('{{$listId}}');
        isInsideModal = !!$el.closest('#modal') || !!$el.closest('[modal]');"
         @mouseenter="$store.app.setId('{{$listId}}')"
         @keydown.window.arrow-down="if (canHandleListKey()) { $event.preventDefault(); selectedRow{{$listId}}++ }"
         @keydown.window.arrow-up="if (canHandleListKey()) { $event.preventDefault(); selectedRow{{$listId}}-- }"
         @keydown.window.enter="if (canHandleListKey()) { $event.preventDefault(); $wire.findListAction(selectedRow{{$listId}}) }"
    >

        @if((!isset($hideHead) || $hideHead !== true) && !$compact)
            <div>
                @include('noerd::components.table.title-search', [
                    'title' => $title,
                    'description' => $description ?? '',
                    'actions' => $actions,
                    'disableSearch' => $disableSearch ?? false,
                    'relations' => $relations ?? [],
                    'action' => $action ?? $listAction,
                    'states' => $this->listStates(),
                    'listFilters' => $this->listFilters(),
                ])
            </div>
        @endif

        @isset($table)
            <div class="min-w-full pb-2 align-middle">
                <div>
                    <div class="flow-root">
                        <div class="-my-2 -mx-6">
                            <div class="inline-block min-w-full py-2 pb-0 align-middle">
                                @php
                                    $totalWeight = array_sum(array_map(fn($c) => $c['width'] ?? 1, $table));
                                    foreach ($table as $i => $col) {
                                        $table[$i]['_widthPercent'] = round((($col['width'] ?? 1) / $totalWeight) * 100, 2);
                                    }
                                @endphp
                                <table class="min-w-full border-separate border-spacing-0">
                                    <thead>
                                    <tr>
                                        @if($showLineNumbers)
                                            <th scope="col" class="sticky top-0 z-10 w-12 border-b border-r border-gray-300 bg-brand-navi/75 px-2 py-3.5 backdrop-blur-sm backdrop-filter"></th>
                                        @endif
                                        @if($multiSelect)
                                            <th scope="col" class="sticky top-0 z-10 w-10 border-b border-r border-gray-300 bg-brand-navi/75 px-3 py-3.5 backdrop-blur-sm backdrop-filter">
                                                <div class="flex items-center justify-center">
                                                    <input type="checkbox"
                                                           wire:key="cb-all-{{ $listId }}-{{ $allVisibleSelected ? 1 : 0 }}"
                                                           wire:click="toggleSelectAllVisible"
                                                           @checked($allVisibleSelected)
                                                           class="block h-4 w-4 cursor-pointer rounded border-gray-300 text-brand-primary focus:ring-brand-border">
                                                </div>
                                            </th>
                                        @endif
                                        @foreach($table as $column)
                                            @include('noerd::components.table.table-sort', [
                                                'width' => $column['_widthPercent'],
                                                'field' => $column['field'],
                                                'label' => $column['label'] ?? '',
                                                'align' => $column['align'] ?? 'left',
                                                'minWidth' => $column['minWidth'] ?? null,
                                                'notSortableColumns' => $notSortableColumns,
                                            ])
                                        @endforeach
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($rows as $key => $row)
                                        <tr :key="{{$key}}"
                                            wire:key="row-{{ $listId }}-{{ $row['id'] ?? $key }}"
                                            :class="{'bg-gray-100!': selectedRow{{$listId}} == {{$key}} }"
                                            @click="selectedRow{{$listId}} = '{{$key}}'"
                                            wire:click="findListAction('{{$key}}')"
                                            class="cursor-pointer group hover:bg-brand-bg border border-black/10">
                                            @if($showLineNumbers)
                                                <td class="w-12 border-b border-r border-gray-300 px-2 py-1 text-right text-xs text-gray-400 select-none">{{ $loop->iteration }}</td>
                                            @endif
                                            @if($multiSelect)
                                                @php $rowChecked = in_array((int) ($row['id'] ?? 0), $selectedRecordIds, true); @endphp
                                                <td class="w-10 border-b border-r border-gray-300 px-3 py-1 text-center" @click.stop>
                                                    {{-- The checked state is part of the wire:key so the input is
                                                         recreated when the selection clears — otherwise a user-toggled
                                                         checkbox keeps its DOM checked state through the morph. --}}
                                                    <div class="flex items-center justify-center">
                                                        <input type="checkbox"
                                                               wire:key="cb-{{ $listId }}-{{ $row['id'] ?? $key }}-{{ $rowChecked ? 1 : 0 }}"
                                                               wire:click.stop="toggleRecordSelection('{{ $row['id'] }}')"
                                                               @checked($rowChecked)
                                                               class="block h-4 w-4 cursor-pointer rounded border-gray-300 text-brand-primary focus:ring-brand-border">
                                                    </div>
                                                </td>
                                            @endif
                                            @foreach($table as $index => $column)
                                                @include('noerd::components.table.table-cell', [
                                                    'row' => $key,
                                                    'column' => $index,
                                                    'label' => $column['label'] ?? '',
                                                    'value' => $row[$column['field']] ?? '',
                                                    'readOnly' => $column['readOnly'] ?? true,
                                                    'id' => $row['id'],
                                                    'columnValue' => $column['field'],
                                                    'type' => $column['type'] ?? 'text',
                                                    'action' => $column['action'] ?? $listAction,
                                                    'actions' => $column['actions'] ?? null,
                                                    'columnConfig' => $column,
                                                    'rowData' => $row,
                                                ])
                                            @endforeach
                                        </tr>
                                    @empty
                                        @php($primaryAction = $actions[0] ?? null)
                                        <tr>
                                            <td colspan="{{ count($table) + ($multiSelect ? 1 : 0) + ($showLineNumbers ? 1 : 0) }}" class="border-b border-black/10 px-6 py-12 text-center">
                                                <p class="text-sm text-gray-500">{{ __('No entries yet') }}</p>
                                                @if($primaryAction)
                                                    <div class="mt-4 flex justify-center">
                                                        <x-noerd::button
                                                            variant="primary"
                                                            :icon="$primaryAction['heroicon'] ?? 'plus'"
                                                            class="h-8"
                                                            wire:click.prevent="{{ $primaryAction['action'] }}(null, {{ Js::from($relations ?? []) }})">
                                                            {{ __($primaryAction['label']) }}
                                                        </x-noerd::button>
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                    @if($summary && $showSummary)
                                        <tfoot>
                                        <tr class="bg-gray-50 font-semibold">
                                            @if($showLineNumbers)
                                                <td class="border-t-2 border-b border-r border-gray-300 py-2 px-2"></td>
                                            @endif
                                            @if($multiSelect)
                                                <td class="border-t-2 border-b border-r border-gray-300 py-2 px-2"></td>
                                            @endif
                                            @foreach($table as $index => $column)
                                                <td class="border-t-2 border-b border-r border-gray-300 py-2 px-1.5 first:pl-6 text-sm @if(($column['align'] ?? 'left') === 'right' || in_array($column['type'] ?? 'text', ['currency', 'number'])) text-right @endif">
                                                    @if(isset($summary[$column['field']]))
                                                        @if(($column['type'] ?? 'text') === 'currency')
                                                            {{ \Noerd\Helpers\CurrencyHelper::format((float) $summary[$column['field']]) }}
                                                        @else
                                                            {{ $summary[$column['field']] }}
                                                        @endif
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                        </tfoot>
                                    @endif
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if(!$compact && isset($rows) && count($rows) > 0 && !is_array($rows))
                <div>
                    {{ $rows->links('noerd::pagination') }}
                </div>
            @endif
        @endisset
    </div>

    @if($multiSelect && $returnsSelection)
        {{-- Picker mode: hand the selection back to the opener --}}
        <x-slot:footer>
            <div class="flex w-full items-center gap-3">
                <span class="text-sm text-gray-600">{{ count($selectedRecordIds) }} {{ __('selected') }}</span>
                <div class="ml-auto flex items-center gap-2">
                    <x-noerd::button variant="secondary" wire:click="$dispatch('closeTopModal')">
                        {{ __('Cancel') }}
                    </x-noerd::button>
                    <x-noerd::button variant="primary" wire:click="confirmRecordSelection">
                        {{ __('Apply selection') }}
                    </x-noerd::button>
                </div>
            </div>
        </x-slot:footer>
    @elseif($multiSelect && ! empty($bulkActions) && count($selectedRecordIds) > 0)
        {{-- Bulk-action mode: run a YAML-defined action on the current selection --}}
        <x-slot:footer>
            <div class="flex w-full items-center gap-3">
                <span class="text-sm text-gray-600">{{ count($selectedRecordIds) }} {{ __('selected') }}</span>
                <div class="ml-auto flex items-center gap-2">
                    @foreach($bulkActions as $bulkAction)
                        @if(! empty($bulkAction['confirm']))
                            <x-noerd::button
                                :variant="($bulkAction['style'] ?? '') ?: 'primary'"
                                :icon="$bulkAction['heroicon'] ?? null"
                                wire:click="{{ $bulkAction['action'] }}"
                                wire:confirm="{{ __($bulkAction['confirm']) }}">
                                {{ __($bulkAction['label']) }}
                            </x-noerd::button>
                        @else
                            <x-noerd::button
                                :variant="($bulkAction['style'] ?? '') ?: 'primary'"
                                :icon="$bulkAction['heroicon'] ?? null"
                                wire:click="{{ $bulkAction['action'] }}">
                                {{ __($bulkAction['label']) }}
                            </x-noerd::button>
                        @endif
                    @endforeach
                </div>
            </div>
        </x-slot:footer>
    @endif
</div>
@endif
