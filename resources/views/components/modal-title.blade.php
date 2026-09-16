@props([
    /** Set to false to suppress the generic list header controls for a NoerdList host. */
    'listControls' => true,
    /** Optional Alpine expression gating the list controls' visibility (e.g. 'currentTab === 2'). */
    'listControlsShow' => null,
    /** Relations forwarded to YAML `action:` buttons — passed through by list-header. */
    'listRelations' => [],
    /**
     * Keep the header on ONE non-wrapping row at every breakpoint instead of stacking
     * below `lg`. Set by the generic list header, whose title row carries only the
     * title and the buttons; search and filters sit on the filter row below it.
     */
    'row' => false,
])

@php
    // The module-contributed DETAIL header actions (HeaderActionsRegistry) do not
    // render here: they sit in the head row of the host's first form block
    // (noerd::components.detail.block-head via DetailHeaderActions), so an embedded
    // detail carries its own icons instead of the hosting page.

    // List headers get their generic controls (search, CSV, registry list actions,
    // YAML action buttons) injected here for every NoerdList host — whether this
    // modal-title came from the generic list-header or from a component's own
    // custom header slot (e.g. a list nested in tab panels). Opt out per header
    // with :listControls="false".
    $listControlsHost = $listControls
        && isset($__livewire)
        && in_array(\Noerd\Traits\NoerdList::class, class_uses_recursive($__livewire), true)
        && ! ($__livewire->quickCreate ?? false)
        && ! ($__livewire->compact ?? false)
        && ! ($__livewire->minimal ?? false)
        ? $__livewire
        : null;

    // What a list header renders is resolved ONCE, on the list itself
    // (NoerdList::headerControls()) — never re-derived from $listSettings here.
    $hasListControls = $listControlsHost?->hasHeaderControls() ?? false;
@endphp
<div @class(['border-b border-gray-300 px-6 py-6', 'lg:flex' => ! $row])>
    <x-noerd::title :row="$row">
        {{ $slot }}
        @if (isset($actions) || $hasListControls)
            <div class="ml-auto flex shrink-0 items-center gap-4" :class="isModal ? modalControlsClass : ''">
                @if ($hasListControls)
                    @if ($listControlsShow)
                        <div x-show="{{ $listControlsShow }}" x-cloak class="flex shrink-0 items-center gap-4">
                    @endif
                    @include('noerd::components.table.list-controls', [
                        'host' => $listControlsHost,
                        'listRelations' => $listRelations,
                    ])
                    @if ($listControlsShow)
                        </div>
                    @endif
                @endif
                {{ $actions ?? '' }}
            </div>
        @endif
    </x-noerd::title>
</div>
