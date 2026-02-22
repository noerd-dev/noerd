@php
    // Auto-extract values from tableConfig if provided
    if (isset($listConfig) && is_array($listConfig)) {
        $title = $title ?? __($listConfig['title'] ?? '');
        $newLabel = $newLabel ?? __($listConfig['newLabel'] ?? '');
        $redirectAction = $redirectAction ?? ($listConfig['redirectAction'] ?? '');
        $disableSearch = $disableSearch ?? ($listConfig['disableSearch'] ?? false);
        $description = $description ?? ($listConfig['description'] ?? false);
        $table = $table ?? ($listConfig['columns'] ?? []);
    }

    // Get listActionMethod from Livewire component property
    $componentAction = $this->listActionMethod ?? 'listAction';
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
    >

        @if(isset($hideHead) && $hideHead === true)
        @else
            <div>
                @include('noerd::components.table.title-search',
                    [
                        'title' => $title,
                        'description' => $description ?? '',
                        'newLabel' => $newLabel ?? null,
                        'disableSearch' => $disableSearch ?? false,
                        'relations' => $relations ?? [],
                        'action' => $action ?? $componentAction,
                        'states' => $this->states(),
                        'tableFilters' => $this->tableFilters(),
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
                                        @foreach($models ?? $rows as $key => $row)
                                            <tr :key="{{$key}}"
                                                :class="{'bg-gray-100!': selectedRow{{$listId}} == {{$key}} }"
                                                @click="selectedRow{{$listId}} = '{{$key}}'"
                                                class="group hover:bg-brand-bg border border-black/10">
                                                @foreach($table as $index => $column)
                                                    @include('noerd::components.table.table-cell',
                                                        [
                                                            'row' => $key,
                                                            'column' => $index,
                                                            'label' => $column['label'] ?? '',
                                                            'value' =>$row[$column['field']] ?? '',
                                                            'redirectAction' => $redirectAction . $row[$primaryKey ?? 'id'],
                                                            'readOnly' => $column['readOnly'] ?? true,
                                                            'id' => $row['id'],
                                                            'columnValue' => $column['field'],
                                                            'type' => $column['type'] ?? 'text',
                                                            'action' => $column['action'] ?? $action ?? $componentAction,
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

            @if((isset($models) && count($models) > 0) || (isset($rows) && count($rows) > 0))
                <div class="py-8">
                    {{isset($models) ? is_array($models) ? '' : $models->links() : ''}}
                    {{isset($rows) ? is_array($rows) ? '' : $rows->links() : ''}}
                </div>
            @endif
        @endisset
    </div>
</div>

