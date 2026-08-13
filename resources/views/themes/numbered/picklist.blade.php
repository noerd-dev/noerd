{{-- Numbered variant of forms.picklist: gray numbered row, right-aligned label, select on the right. --}}
@props([
    'field' => null,
    'name' => '',
    'picklistField' => '',
    'live' => false,
    'readonly' => false,
    'placeholder' => null,
])

@php
    $name = $field['name'] ?? $name;
    $picklistField = $field['picklistField'] ?? $picklistField;
    $live = $field['live'] ?? $live;
    $readonly = $field['readonly'] ?? $readonly;
    $placeholder = $field['placeholder'] ?? $placeholder;
@endphp

<x-noerd::detail.numbered-row :field="$field">
    <select
        @if ($live)
            wire:model.live.debounce="{{ $name }}"
        @else
            wire:model="{{ $name }}"
        @endif
        class="block h-9 w-full appearance-none rounded-none border border-zinc-400 bg-white py-1 ps-2 pe-2 text-base text-zinc-700 placeholder-zinc-400 focus:border-dotted focus:border-zinc-600 focus:ring-0 focus:outline-none disabled:text-zinc-500 disabled:placeholder-zinc-400/70 sm:text-sm"
        @if ($readonly) disabled @endif
        id="{{ $name }}"
    >
        @if ($placeholder)
            <option value="">{{ __($placeholder) }}</option>
        @endif
        @foreach ($this->resolvePicklistOptions($picklistField) as $key => $value)
            <option value="{{ $key }}">{{ $value }}</option>
        @endforeach
    </select>
</x-noerd::detail.numbered-row>
