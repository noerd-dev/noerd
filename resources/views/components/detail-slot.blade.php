@props(['name', 'modelId' => null])

@php
    // Named extension slot for detail components: optional modules register
    // Livewire components for a slot name (DetailSlotsRegistry), the hosting
    // detail only marks the position. Children are mounted with stable keys and
    // mount-only params, so subsequent parent renders emit a memo placeholder
    // and morph leaves the child DOM untouched. Slot children holding unsaved
    // draft state lose it when the modal stack updates (children remount), so
    // hosts should not open nested modals around a filled slot.
    // Quick-create dialogs stay slim and render no slots.
    $slotHost = isset($__livewire) && ! ($__livewire->quickCreate ?? false)
        ? $__livewire
        : null;
    $slotComponents = $slotHost !== null
        ? app(\Noerd\Services\DetailSlotsRegistry::class)->for($name)
        : [];
@endphp

@foreach ($slotComponents as $slotComponent)
    @livewire($slotComponent, [
        'modelId' => $modelId,
        'hostComponent' => $slotHost->getName(),
    ], key('detail-slot-' . $name . '-' . $slotComponent))
@endforeach
