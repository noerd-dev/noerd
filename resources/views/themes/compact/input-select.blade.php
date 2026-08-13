{{-- Compact variant of forms.input-select: label sits to the LEFT of the select. --}}
@props([
    'field' => null,
    'name' => '',
    'label' => '',
    'options' => [],
    'live' => false,
    'readonly' => false,
    'required' => false,
])

@php
    $name = $field['name'] ?? $name;
    $label = $field['label'] ?? $label;
    $options = $field['options'] ?? $options;
    $live = $field['live'] ?? $live;
    $readonly = $field['readonly'] ?? $readonly;
    $required = $field['required'] ?? $required;
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
            @foreach ($options as $option)
                @isset($option['value'])
                    <option value="{{ $option['value'] }}">{{ __($option['label']) }}</option>
                @else
                    <option>{{ __($option) }}</option>
                @endisset
            @endforeach
        </select>
        <x-noerd::input-error :messages="$errors->get($name)" class="mt-2" />
    </div>
</div>
