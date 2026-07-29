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
        @if($live)
            wire:model.live.debounce="{{ $name }}"
        @else
            wire:model="{{ $name }}"
        @endif
        class="w-full border border-zinc-200 rounded-sm block appearance-none text-base sm:text-sm py-0.5 h-7 ps-2 pe-2 bg-white text-zinc-700 disabled:text-zinc-500 placeholder-zinc-400 disabled:placeholder-zinc-400/70 focus:outline-none focus:ring-1 focus:ring-brand-border"
        id="{{ $name }}"
    >
        @if($placeholder)
            <option value="">{{ __($placeholder) }}</option>
        @endif
        @foreach($this->resolvePicklistOptions($picklistField) as $key => $value)
            <option value="{{ $key }}">{{ $value }}</option>
        @endforeach
    </select>
</x-noerd::detail.numbered-row>
