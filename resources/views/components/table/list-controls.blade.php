{{--
    Generic list header controls for a header that does NOT collapse: CSV export,
    search, registry list actions and the YAML `actions` buttons, in one row.
    Included by x-noerd::modal-title for a NoerdList host that brings its own custom
    header slot. The generic list-header renders the same partials itself instead,
    so the collapsing half can double as the filter drawer — see list-header.

    Expects: $host (the NoerdList Livewire component), $listRelations. Positioning
    (ml-auto, modal controls offset) is owned by the modal-title wrapper — never add
    offsets here.
--}}
@php
    $controls = $host->headerControls();
    $controlArguments = ['host' => $host, 'controls' => $controls, 'listRelations' => $listRelations];
@endphp

@if ($host->hasCollapsibleControls())
    <div class="flex items-center gap-2">
        @if ($controls['search'])
            @include('noerd::components.table.list-search', $controlArguments)
        @endif
        @include('noerd::components.table.list-controls-secondary', $controlArguments)
    </div>
@endif

@include('noerd::components.table.list-controls-primary', $controlArguments)
