@props(['layout' => null, 'modelId' => null, 'showBlock' => true])

@php
    $tabs = $layout['tabs'] ?? [['number' => 1]];
    $fields = $layout['fields'] ?? [];
@endphp

{{-- Tab Navigation --}}
<x-noerd::tabs :layout="$layout" :modelId="$modelId" />

{{-- Tab Content Panels --}}
<div class="grid [&>*]:col-start-1 [&>*]:row-start-1">
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

                @include('noerd::components.detail.block', $tabLayout)
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
