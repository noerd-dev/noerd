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
                @foreach ($detailHeaderActions as $detailHeaderAction)
                    @livewire($detailHeaderAction, [
                        'model' => $headerActionHost->detailModel ?? null,
                        'component' => $headerActionHost->getName(),
                    ], key('detail-header-action-' . $detailHeaderAction))
                @endforeach
                {{ $actions ?? '' }}
            </div>
        @endif
    </x-noerd::title>
</div>
