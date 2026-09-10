{{--
    The registry list actions (HeaderActionsRegistry::listActions(), e.g. a
    module's layout or object manager buttons). The generic list header
    renders them in the right group of its filter row; a list host with its own
    custom header gets them from list-controls, after the search and the buttons.

    Expects: $host (the NoerdList Livewire component), $controls (see
    NoerdList::headerControls()).
--}}
@if ($controls['registry'] !== [])
    {{-- Collapses when every action hid itself: the children are server-rendered
         before Alpine initializes, so probing for a button is reliable. Without
         this an empty wrapper would still carry the parent's gap. --}}
    <div
        x-data="{ hasActions: false }"
        x-init="hasActions = $el.querySelector('button') !== null"
        x-show="hasActions"
        x-cloak
        class="flex shrink-0 items-center gap-2"
    >
        @foreach ($controls['registry'] as $listHeaderAction)
            @livewire($listHeaderAction, [
                'model' => $host->listModel ?? null,
                'component' => $host->getComponentName(),
            ], key('list-header-action-' . $listHeaderAction))
        @endforeach
    </div>
@endif
