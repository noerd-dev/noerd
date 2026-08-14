@props([
    /** Set to false to suppress the generic list header controls for a NoerdList host. */
    'listControls' => true,
    /** Optional Alpine expression gating the list controls' visibility (e.g. 'currentTab === 2'). */
    'listControlsShow' => null,
    /** Relations forwarded to YAML `action:` buttons — passed through by list-header. */
    'listRelations' => [],
])

@php
    // Detail headers get the module-contributed header actions (HeaderActionsRegistry)
    // injected here — modal-title is the one generic component every *-detail header
    // goes through. The slim quick-create dialogs are no place for admin tooling.
    $headerActionHost = isset($__livewire) && str_ends_with($__livewire->getName(), '-detail')
        && ! ($__livewire->quickCreate ?? false)
        ? $__livewire
        : null;
    $detailHeaderActions = $headerActionHost !== null
        ? app(\Noerd\Services\HeaderActionsRegistry::class)->detailActions()
        : [];

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

    $listControlsConfig = $listControlsHost?->builtListConfig() ?? [];
    $listControlsSettings = $listControlsConfig['listSettings'] ?? [];
    $listObjectAccessDenied = $listControlsConfig['objectAccessDenied'] ?? false;
    $listIsPicker = $listControlsHost->returnsSelection ?? false;
    $hasListControls = $listControlsHost !== null && (
        (! ($listControlsSettings['disableSearch'] ?? false) && ! $listObjectAccessDenied)
        || ($listControlsHost->enableCsvExport ?? false)
        || (! $listIsPicker && ($listControlsSettings['actions'] ?? []) !== [])
        || (! $listIsPicker && app(\Noerd\Services\HeaderActionsRegistry::class)->listActions() !== [])
    );
@endphp
<div class="border-b border-gray-300 px-6 py-6 lg:flex">
    <x-noerd::title>
        {{ $slot }}
        @if (isset($actions) || $detailHeaderActions !== [] || $hasListControls)
            <div class="ml-auto flex shrink-0 items-center gap-4" :class="isModal ? modalControlsClass : ''">
                @if ($hasListControls)
                    @if ($listControlsShow)
                        <div x-show="{{ $listControlsShow }}" x-cloak class="flex shrink-0 items-center gap-4">
                    @endif
                    @include('noerd::components.table.list-controls', [
                        'host' => $listControlsHost,
                        'listSettings' => $listControlsSettings,
                        'objectAccessDenied' => $listObjectAccessDenied,
                        'listRelations' => $listRelations,
                    ])
                    @if ($listControlsShow)
                        </div>
                    @endif
                @endif
                @if ($detailHeaderActions !== [])
                    {{-- Grouped so the icon buttons keep the 8px rhythm of the modal
                         panel controls. Collapses when every action hid itself —
                         otherwise the empty wrapper would still eat the parent gap. --}}
                    <div
                        x-data="{ hasActions: false }"
                        x-init="hasActions = $el.querySelector('button') !== null"
                        x-show="hasActions"
                        x-cloak
                        class="flex shrink-0 items-center gap-2"
                    >
                        @foreach ($detailHeaderActions as $detailHeaderAction)
                            @livewire($detailHeaderAction, [
                                'model' => $headerActionHost->detailModel ?? null,
                                'component' => $headerActionHost->getName(),
                            ], key('detail-header-action-' . $detailHeaderAction))
                        @endforeach
                    </div>
                @endif
                {{ $actions ?? '' }}
            </div>
        @endif
    </x-noerd::title>
</div>
