@props([
    'listConfig' => null,
    'relations' => [],
    'summary' => null,
])

@php
    // Auto-fetch listConfig from parent Livewire component if not provided
    $listConfig = $listConfig ?? $this->with()['listConfig'] ?? [];

    // Extract values from listConfig
    $listId = $listConfig['listId'] ?? '';
    $sortField = $listConfig['sortField'] ?? 'id';
    $sortAsc = $listConfig['sortAsc'] ?? false;
    $rows = $listConfig['rows'] ?? [];
    $listSettings = $listConfig['listSettings'] ?? [];

    // Extract values from listSettings
    $title = __($listSettings['title'] ?? '');
    $newLabel = __($listSettings['newLabel'] ?? '');
    $redirectAction = $listSettings['redirectAction'] ?? '';
    $disableSearch = $listSettings['disableSearch'] ?? false;
    $description = $listSettings['description'] ?? false;
    $table = $listSettings['columns'] ?? [];

    $listAction = $this->listActionMethod ?? 'listAction';
@endphp

<div>
    @include('noerd::components.table.list-header')

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
         @record-navigated.window="
            const rowIds = @js(is_array($rows) ? array_column($rows, 'id') : $rows->getCollection()->pluck('id')->values()->toArray());
            const idx = rowIds.indexOf(parseInt($event.detail.id));
            if (idx !== -1) selectedRow{{$listId}} = idx;
         "
    >

        @if(!isset($hideHead) || $hideHead !== true)
            <div>
                @include('noerd::components.table.title-search', [
                    'title' => $title,
                    'description' => $description ?? '',
                    'newLabel' => $newLabel ?? null,
                    'disableSearch' => $disableSearch ?? false,
                    'relations' => $relations ?? [],
                    'action' => $action ?? $listAction,
                    'states' => $this->listStates(),
                    'listFilters' => $this->listFilters(),
                ])
            </div>
        @endif

        @isset($table)
            <div class="relative">

                <div class=" min-w-full pb-2 align-middle overflow-visible">
                    <div class="overflow-visible">

                        <div class="flow-root">
                            <div class="-my-2 -mx-6">
                                <div class="inline-block min-w-full py-2 align-middle">
                                    <table class="min-w-full border-separate border-spacing-0">
                                        <thead>
                                        <tr>
                                            @foreach($table as $column)
                                                @include('noerd::components.table.table-sort', [
                                                    'width' => $column['width'] ?? 10,
                                                    'field' => $column['field'],
                                                    'label' => $column['label'] ?? '',
                                                    'align' => $column['align'] ?? 'left',
                                                    'minWidth' => $column['minWidth'] ?? null,
                                                ])
                                            @endforeach
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($rows as $key => $row)
                                            <tr :key="{{$key}}"
                                                :class="{'bg-gray-100!': selectedRow{{$listId}} == {{$key}} }"
                                                @click="selectedRow{{$listId}} = '{{$key}}'"
                                                class="group hover:bg-brand-bg border border-black/10">
                                                @foreach($table as $index => $column)
                                                    @include('noerd::components.table.table-cell', [
                                                        'row' => $key,
                                                        'column' => $index,
                                                        'label' => $column['label'] ?? '',
                                                        'value' => $row[$column['field']] ?? '',
                                                        'redirectAction' => $redirectAction . $row['id'],
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
                                        @endforeach
                                        </tbody>
                                        @if($summary)
                                            <tfoot>
                                                <tr class="bg-gray-50 font-semibold">
                                                    @foreach($table as $index => $column)
                                                        <td class="border-t-2 border-b border-r border-gray-300 py-2 px-1.5 first:pl-6 text-sm @if(($column['align'] ?? 'left') === 'right' || in_array($column['type'] ?? 'text', ['currency', 'number'])) text-right @endif">
                                                            @if(isset($summary[$column['field']]))
                                                                @if(($column['type'] ?? 'text') === 'currency')
                                                                    {{ number_format((float) $summary[$column['field']], 2, ',', '.') }} €
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
            </div>

            @if(isset($rows) && count($rows) > 0 && (is_array($rows) ? '' : $rows->links()) )
                <div class="py-8">
                    {{ is_array($rows) ? '' : $rows->links() }}
                </div>
            @endif
        @endisset
    </div>
</div>
