@php
    // Detail headers get the module-contributed header actions (HeaderActionsRegistry)
    // injected here — modal-title is the one generic component every *-detail header
    // goes through. Lists inject theirs in list-header (their component ends in -list),
    // and the slim quick-create dialogs are no place for admin tooling.
    $headerActionHost = isset($__livewire) && str_ends_with($__livewire->getName(), '-detail')
        && ! ($__livewire->quickCreate ?? false)
        ? $__livewire
        : null;
    $detailHeaderActions = $headerActionHost !== null
        ? app(\Noerd\Services\HeaderActionsRegistry::class)->detailActions()
        : [];
@endphp
<div class="border-b border-gray-300 px-6 py-6 lg:flex">
    <x-noerd::title>
        {{ $slot }}
        @if (isset($actions) || $detailHeaderActions !== [])
            <div class="ml-auto flex shrink-0 items-center gap-4" :class="isModal ? modalControlsClass : ''">
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
