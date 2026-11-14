{{-- Numbered variant of the button field: gray numbered row, the button sits in the control slot. --}}
@php
    // The name is the component method the button calls: only identifier
    // characters reach the wire:click expression (as with list row actions).
    $name = (string) preg_replace('/[^A-Za-z0-9_]/', '', (string) ($field['name'] ?? $name ?? ''));
    $label = $field['label'] ?? $label ?? '';
    $readonly = $field['readonly'] ?? false;
@endphp

<x-noerd::detail.numbered-row :field="array_merge($field ?? [], ['label' => ''])">
    @if ($readonly)
        <x-noerd::button theme="numbered" disabled type="button"> {{ __($label) }} </x-noerd::button>
    @else
        <x-noerd::button theme="numbered" wire:click="{{ $name }}" type="button"> {{ __($label) }} </x-noerd::button>
    @endif
</x-noerd::detail.numbered-row>
