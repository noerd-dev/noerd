{{--
    Generic list header controls for a list host that brings its OWN custom header
    slot (e.g. a list nested in tab panels): search, CSV export, the `style:
    secondary` actions, the registry list actions and the primary YAML buttons, in
    one row. Included by x-noerd::modal-title. The generic list-header renders the
    same partials itself, spread over its title row and its filter row — see
    list-header.

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

@include('noerd::components.table.list-controls-registry', $controlArguments)

@include('noerd::components.table.list-controls-primary', $controlArguments)
