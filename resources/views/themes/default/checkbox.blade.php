@props([
    'field' => null,
    'name' => '',
    'label' => '',
    'readonly' => false,
    'live' => false,
])

@php
    $name = $field['name'] ?? $name;
    $label = $field['label'] ?? $label;
    $readonly = $field['readonly'] ?? $readonly;
    $live = $field['live'] ?? $live;

    // Get current value and convert to boolean (handles "1"/"0" strings without model cast)
    $currentValue = data_get($this, $name);
    $isChecked = filter_var($currentValue, FILTER_VALIDATE_BOOLEAN);
@endphp

<div class="mt-auto flex h-full">
    <div class="relative my-auto flex items-start">
        <div class="flex h-6 items-center">
            <input
                @if ($readonly) disabled @endif
                @if ($live)
                    wire:model.live.debounce="{{ $name }}"
                @else
                    wire:model="{{ $name }}"
                @endif
                :checked="{{ $isChecked ? 'true' : 'false' }}"
                id="{{ $name }}"
                type="checkbox"
                class="text-brand-primary focus:ring-brand-border h-4 w-4 rounded-sm border border-gray-300"
            />
        </div>
        <div class="ml-3 text-sm leading-6">
            <label for="{{ $name }}" class="font-medium text-gray-900">{{ __($label) }}</label>
        </div>
    </div>

    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2" />
</div>
