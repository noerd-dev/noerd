@props(['layout' => null, 'modelId' => null, 'showBlock' => true, 'detailData' => null, 'quickCreate' => null])

@php
    // quickCreate may be passed explicitly, but normally rides inside the layout
    // (set from the detail YAML `quickCreate: true` and resolved per record by NoerdDetail),
    // so detail blades don't need to wire the prop. An explicit prop still wins.
    $quickCreate = $quickCreate ?? ($layout['quickCreate'] ?? false);
@endphp

@if($quickCreate)
    @php
        // Quick-create renders only the mandatory fields (required, or explicitly
        // opted in via `quickCreate: true`) as a single vertical column. Tabs and
        // the block heading are dropped so the modal stays compact under its title.
        $quickFields = array_values(array_filter(
            $layout['fields'] ?? [],
            fn ($field) => ($field['required'] ?? false) || ($field['quickCreate'] ?? false),
        ));
        $quickFields = array_map(function ($field) {
            $field['colspan'] = 12;

            return $field;
        }, $quickFields);
        $quickLayout = array_merge($layout ?? [], ['fields' => $quickFields, 'cols' => 12]);
        unset($quickLayout['tabs'], $quickLayout['title'], $quickLayout['description']);
    @endphp

    <div class="pt-6">
        @include('noerd::components.detail.block', array_merge($quickLayout, ['detailData' => $detailData]))
    </div>
@else
    @php
        $tabs = $layout['tabs'] ?? [['number' => 1]];
        $fields = $layout['fields'] ?? [];
    @endphp

    {{-- Tab Navigation --}}
    <x-noerd::tabs :layout="$layout" :modelId="$modelId" />

    {{-- Tab Content Panels --}}
    <div class="grid first:pt-6 [&>*]:col-start-1 [&>*]:row-start-1">
    @foreach($tabs as $tab)
        @php
            // Skip tabs with 'component' - they open as modals, not inline content
            if (isset($tab['component'])) {
                continue;
            }

            $showTab = true;
            if (isset($tab['requiresId']) && $tab['requiresId'] && !$modelId) {
                $showTab = false;
            }
            if (isset($tab['permission'])) {
                $permissionModel = $tab['permissionModel'] ?? null;
                $showTab = $showTab && Gate::allows($tab['permission'], $permissionModel);
            }
            if (isset($tab['viewExists']) && !View::exists($tab['viewExists'])) {
                $showTab = false;
            }
        @endphp

        @if($showTab)
            <div :class="currentTab === {{ $tab['number'] }} ? 'visible' : 'invisible pointer-events-none'">
                {{-- Render prepend slot for this tab if it exists (e.g., prependTab1, prependTab2, etc.) --}}
                @php
                    $prependSlotName = 'prependTab' . $tab['number'];
                @endphp
                @if(isset($$prependSlotName))
                    {{ $$prependSlotName }}
                @endif

                @if($showBlock)
                    @php
                        $tabFields = array_filter($fields, fn($field) => ($field['tab'] ?? 1) === $tab['number']);
                        $tabLayout = array_merge($layout, ['fields' => array_values($tabFields)]);
                        if ($tab['number'] !== 1) {
                            unset($tabLayout['title'], $tabLayout['description']);
                        }
                    @endphp

                    @include('noerd::components.detail.block', array_merge($tabLayout, ['detailData' => $detailData]))
                @endif

                {{-- Render named slot for this tab if it exists (e.g., tab1, tab2, etc.) --}}
                @php
                    $slotName = 'tab' . $tab['number'];
                @endphp
                @if(isset($$slotName))
                    {{ $$slotName }}
                @endif
            </div>
        @endif
    @endforeach
    </div>
@endif
