{{-- Compact variant of forms.picklist: label sits to the LEFT of the select. --}}
@props([
    'field' => null,
    'name' => '',
    'label' => '',
    'picklistField' => '',
    'live' => false,
    'readonly' => false,
    'required' => false,
    'placeholder' => null,
])

@php
    $name = $field['name'] ?? $name;
    $label = $field['label'] ?? $label;
    $picklistField = $field['picklistField'] ?? $picklistField;
    $live = $field['live'] ?? $live;
    $readonly = $field['readonly'] ?? $readonly;
    $required = $field['required'] ?? $required;
    $placeholder = $field['placeholder'] ?? $placeholder;
@endphp

<div class="flex items-center gap-2">
    <x-noerd::input-label
        for="{{ $name }}"
        :value="__($label)"
        :required="$required"
        :title="__($label)"
        class="w-36 shrink-0 truncate !pb-0"
    />
    <div class="min-w-0 flex-1">
        <select
            @if ($live)
                wire:model.live.debounce="{{ $name }}"
            @else
                wire:model="{{ $name }}"
            @endif
            class="focus:ring-brand-border block h-7 w-full appearance-none rounded-sm border border-zinc-200 bg-white py-0.5 ps-2 pe-2 text-base text-zinc-700 placeholder-zinc-400 focus:ring-1 focus:outline-none disabled:text-zinc-500 disabled:placeholder-zinc-400/70 sm:text-sm"
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
        <x-noerd::input-error :messages="$errors->get($name)" class="mt-2" />
    </div>
</div>
