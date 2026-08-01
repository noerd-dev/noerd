{{-- Numbered variant of the button field: gray numbered row, the button sits in the control slot. --}}
@php
    $name = $field['name'] ?? $name ?? '';
    $label = $field['label'] ?? $label ?? '';
@endphp

<x-noerd::detail.numbered-row :field="array_merge($field ?? [], ['label' => ''])">
    <x-noerd::button theme="numbered" wire:click="{{ $name }}" type="button"> {{ __($label) }} </x-noerd::button>
</x-noerd::detail.numbered-row>
