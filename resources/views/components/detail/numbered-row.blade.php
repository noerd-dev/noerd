{{-- Shared row chrome for the 'numbered' theme: gray full-width row with a leading row
     number, a right-aligned label and the control in a fixed-width slot on the right. The
     element templates in themes/numbered/ only provide the bare control via the slot. --}}
@props([
    'field' => null,
    'labelTop' => false,
])

@php
    $name = $field['name'] ?? '';
    $label = $field['label'] ?? '';
    $required = $field['required'] ?? false;
    $number = $field['number'] ?? null;
@endphp

<div class="flex {{ $labelTop ? 'items-start' : 'items-center' }} gap-3 bg-zinc-100 rounded-sm px-2 py-1.5 w-full">
    <div class="w-8 shrink-0 text-right text-sm text-zinc-400 tabular-nums {{ $labelTop ? 'pt-1' : '' }}">
        {{ $number }}
    </div>

    <x-noerd::input-label
        for="{{ $name }}"
        :value="__($label)"
        :required="$required"
        :title="__($label)"
        class="!pb-0 !font-normal flex-1 min-w-0 text-right truncate {{ $labelTop ? 'pt-1' : '' }}"
    />

    <div class="w-1/2 shrink-0">
        {{ $slot }}
        <x-noerd::input-error :messages="$errors->get($name)" class="mt-2" />
    </div>
</div>
