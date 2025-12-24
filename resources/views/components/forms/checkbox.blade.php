@props([
    'field' => null,
    'name' => '',
    'label' => '',
    'disabled' => false,
    'live' => false,
])

@php
    $name = $field['name'] ?? $name;
    $label = $field['label'] ?? $label;
    $disabled = $field['disabled'] ?? $disabled;
    $live = $field['live'] ?? $live;
@endphp

<div class="mt-auto h-full flex">
    <div class="relative flex items-start my-auto">
        <div class="flex h-6 items-center">
            <input
                @if($disabled) disabled @endif
                @if($live)
                    wire:model.live.debounce="{{ $name }}"
                @else
                    wire:model="{{ $name }}"
                @endif
                id="{{ $name }}"
                type="checkbox"
                class="h-4 w-4 rounded-sm border border-gray-300 text-brand-primary focus:ring-brand-border"
            >
        </div>
        <div class="ml-3 text-sm leading-6">
            <label for="{{ $name }}" class="font-medium text-gray-900">{{ __($label) }}</label>
        </div>
    </div>

    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2"/>
</div>
