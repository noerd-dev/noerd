@props([
    'field' => null,
    'name' => '',
    'label' => '',
])

@php
    $name = $field['name'] ?? $name;
    $label = $field['label'] ?? $label;
@endphp

<div class="mt-auto flex h-full">
    <x-noerd::buttons.primary wire:click="{{ $name }}" class="mt-auto !h-[40px]">
        {{ $label }}
    </x-noerd::buttons.primary>
</div>
