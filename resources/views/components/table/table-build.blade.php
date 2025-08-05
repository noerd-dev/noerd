<div x-data="{
        selectedRow{{$tableId}}: -1,
        beobachte(el) {
            let observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {

                        this.selectedRow{{$tableId}}++;
                        $store.app.setId('{{$tableId}}')
                    }
                });
            }, {
                threshold: 0, // so früh wie möglich triggern
                rootMargin: '0px' // ggf. '100px' für Vorlauf
            });

            observer.observe(el);
        }
    }"
     x-init="beobachte($el)"
     @mouseenter="$store.app.setId('{{$tableId}}')"
     @keydown.window.arrow-down.prevent="($store.app.currentId == '{{$tableId}}') && selectedRow{{$tableId}}++"
     @keydown.window.arrow-up.prevent="($store.app.currentId == '{{$tableId}}') && selectedRow{{$tableId}}--"
     @keydown.window.enter.prevent="($store.app.currentId == '{{$tableId}}') && $wire.findTableAction(selectedRow{{$tableId}})"
>
    <script>
        function sichtbarkeitsBeobachter(callback) {
            return {
                beobachte(el) {
                    let observer = new IntersectionObserver((entries, obs) => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting) {
                                callback();
                                obs.unobserve(entry.target);
                            }
                        });
                    }, {threshold: 0.5});

                    observer.observe(el);
                }
            }
        }
    </script>

    @if(isset($hideHead) && $hideHead === true)
    @else
        <x-slot:table>
            <div class="bg-white p-8 pt-8">
                @include('noerd::components.table.title-search',
                    [
                        'title' => $title,
                        'description' => $description ?? '',
                        'newLabel' => $newLabel ?? null,
                        'disableSearch' => $disableSearch ?? false,
                        'relationId' => $relationId ?? null,
                        'action' => $action ?? 'tableAction',
                        'states' => $this->states(),
                        'tableFilters' => $this->tableFilters(),
                    ])
            </div>
        </x-slot:table>
    @endif

    @isset($table)
        <div class="relative">

            <div class=" min-w-full pb-2 align-middle overflow-visible">
                <div class="overflow-visible">
                    <table class="wrapper w-full border-separate border-spacing-0">
                        <thead bgcolor="red" class="!bg-green-200 sticky top-0 ">
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
                                :class="{'bg-blue-50!': selectedRow{{$tableId}} == {{$key}} }"
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
                                            'action' => $column['action'] ?? $action ?? 'tableAction',
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

        <div class="py-8">
            {{isset($models) ? is_array($models) ? '' : $models->links() : ''}}
            {{isset($rows) ? is_array($rows) ? '' : $rows->links() : ''}}
        </div>
    @endisset

</div>

