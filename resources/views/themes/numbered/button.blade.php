{{-- Numbered variant of the button field: gray numbered row, the button sits in the control slot. --}}
@php
    $name = $field['name'] ?? $name ?? '';
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
