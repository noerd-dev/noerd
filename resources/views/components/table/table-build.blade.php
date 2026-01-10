@php
    // Auto-extract values from tableConfig if provided
    if (isset($tableConfig) && is_array($tableConfig)) {
        $title = $title ?? __($tableConfig['title'] ?? '');
        $newLabel = $newLabel ?? __($tableConfig['newLabel'] ?? '');
        $redirectAction = $redirectAction ?? ($tableConfig['redirectAction'] ?? '');
        $disableSearch = $disableSearch ?? ($tableConfig['disableSearch'] ?? false);
        $description = $description ?? ($tableConfig['description'] ?? false);
        $table = $table ?? ($tableConfig['columns'] ?? []);
    }

    // Get tableActionMethod from Livewire component property
    $componentAction = $this->tableActionMethod ?? 'tableAction';
@endphp

<div>
    @include('noerd::components.table.list-header')

    <div x-data="{
        selectedRow{{$tableId}}: 0,
        isInsideModal: false,
    }"
         x-init="
        $store.app.setId('{{$tableId}}');
        isInsideModal = !!$el.closest('#modal') || !!$el.closest('[modal]');"
         @mouseenter="$store.app.setId('{{$tableId}}')"
         @keydown.window.arrow-down.prevent="($store.app.currentId == '{{$tableId}}') && (isInsideModal || !$store.app.modalOpen) && selectedRow{{$tableId}}++"
         @keydown.window.arrow-up.prevent="($store.app.currentId == '{{$tableId}}') && (isInsideModal || !$store.app.modalOpen) && selectedRow{{$tableId}}--"
         @keydown.window.enter.prevent="($store.app.currentId == '{{$tableId}}') && (isInsideModal || !$store.app.modalOpen) && $wire.findTableAction(selectedRow{{$tableId}})"
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
                        'relationId' => $relationId ?? null,
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
                                                :class="{'bg-gray-100!': selectedRow{{$tableId}} == {{$key}} }"
                                                @click="selectedRow{{$tableId}} = '{{$key}}'"
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
                                                       ])
                                                @endforeach
                                            </tr>

                                            <!-- START CUSTOM -->
                                            @isset($secondLine)
                                                @if($secondLine === 'stage')
                                                    <tr>
                                                        <td colspan="6" class="pt-3 pb-3">
                                                            @include('project::livewire.stage-line', ['project' => $row])
                                                        </td>
                                                    </tr>
                                                @endif
                                            @endisset
                                            @isset($thirdLine)
                                                @if($thirdLine === 'wood_sum')
                                                    <tr>
                                                        <td colspan="6" class="pt-3 pb-3">
                                                            @include('project::livewire.wood-sum-line', ['project' => $row])
                                                        </td>
                                                    </tr>
                                                @endif
                                            @endisset
                                            <!-- END CUSTOM -->

                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        {{--
                        <table class="wrapper  w-full border-separate border-spacing-0">
                            <thead class="sticky top-0">
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
                                    :class="{'bg-gray-100!': selectedRow{{$tableId}} == {{$key}} }"
                                    @click="selectedRow{{$tableId}} = '{{$key}}'"
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
                                           ])
                                    @endforeach
                                </tr>

                                <!-- START CUSTOM -->
                                @isset($secondLine)
                                    @if($secondLine === 'stage')
                                        <tr>
                                            <td colspan="6" class="pt-3 pb-3">
                                                @include('project::livewire.stage-line', ['project' => $row])
                                            </td>
                                        </tr>
                                    @endif
                                @endisset
                                @isset($thirdLine)
                                    @if($thirdLine === 'wood_sum')
                                        <tr>
                                            <td colspan="6" class="pt-3 pb-3">
                                                @include('project::livewire.wood-sum-line', ['project' => $row])
                                            </td>
                                        </tr>
                                    @endif
                                @endisset
                                <!-- END CUSTOM -->

                            @endforeach
                            </tbody>
                        </table>
                        --}}
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

