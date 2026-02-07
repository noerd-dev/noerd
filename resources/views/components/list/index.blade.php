@props([
    'listConfig' => null,
    'relationId' => null,
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
    }"
         x-init="
        $store.app.setId('{{$listId}}');
        isInsideModal = !!$el.closest('#modal') || !!$el.closest('[modal]');"
         @mouseenter="$store.app.setId('{{$listId}}')"
         @keydown.window.arrow-down.prevent="($store.app.currentId == '{{$listId}}') && (isInsideModal || !$store.app.modalOpen) && selectedRow{{$listId}}++"
         @keydown.window.arrow-up.prevent="($store.app.currentId == '{{$listId}}') && (isInsideModal || !$store.app.modalOpen) && selectedRow{{$listId}}--"
         @keydown.window.enter.prevent="($store.app.currentId == '{{$listId}}') && (isInsideModal || !$store.app.modalOpen) && $wire.findListAction(selectedRow{{$listId}})"
    >

        @if(!isset($hideHead) || $hideHead !== true)
            <div>
                @include('noerd::components.table.title-search', [
                    'title' => $title,
                    'description' => $description ?? '',
                    'newLabel' => $newLabel ?? null,
                    'disableSearch' => $disableSearch ?? false,
                    'relationId' => $relationId ?? null,
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
                                    </table>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>
            </div>

            @if(isset($rows) && count($rows) > 0)
                <div class="py-8">
                    {{ is_array($rows) ? '' : $rows->links() }}
                </div>
            @endif
        @endisset
    </div>
</div>
