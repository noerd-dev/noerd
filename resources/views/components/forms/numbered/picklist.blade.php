{{-- Numbered variant of forms.picklist: gray numbered row, right-aligned label, select on the right. --}}
@props([
    'field' => null,
    'name' => '',
    'picklistField' => '',
    'live' => false,
    'placeholder' => null,
])

@php
    $name = $field['name'] ?? $name;
    $picklistField = $field['picklistField'] ?? $picklistField;
    $live = $field['live'] ?? $live;
    $placeholder = $field['placeholder'] ?? $placeholder;
@endphp

<x-noerd::detail.numbered-row :field="$field">
    <select
        @if ($live)
            wire:model.live.debounce="{{ $name }}"
        @else
            wire:model="{{ $name }}"
        @endif
        class="focus:ring-brand-border block h-7 w-full appearance-none rounded-sm border border-zinc-200 bg-white py-0.5 ps-2 pe-2 text-base text-zinc-700 placeholder-zinc-400 focus:ring-1 focus:outline-none disabled:text-zinc-500 disabled:placeholder-zinc-400/70 sm:text-sm"
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
