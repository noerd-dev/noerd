{{-- Compact variant of forms.picklist: label sits to the LEFT of the select. --}}
@props([
    'field' => null,
    'name' => '',
    'label' => '',
    'picklistField' => '',
    'live' => false,
    'required' => false,
    'placeholder' => null,
])

@php
    $name = $field['name'] ?? $name;
    $label = $field['label'] ?? $label;
    $picklistField = $field['picklistField'] ?? $picklistField;
    $live = $field['live'] ?? $live;
    $required = $field['required'] ?? $required;
    $placeholder = $field['placeholder'] ?? $placeholder;
@endphp

<div class="flex items-center gap-2">
    <x-noerd::input-label for="{{ $name }}" :value="__($label)" :required="$required" :title="__($label)" class="!pb-0 w-36 shrink-0 truncate"/>
    <div class="flex-1 min-w-0">
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
        <x-noerd::input-error :messages="$errors->get($name)" class="mt-2"/>
    </div>
</div>
