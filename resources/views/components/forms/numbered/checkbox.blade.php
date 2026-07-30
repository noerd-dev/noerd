{{-- Numbered variant of forms.checkbox: gray numbered row; the checkbox keeps its horizontal
     layout (checkbox left of its label) inside the control slot. --}}
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

    // The row label already names the field; the control slot shows only the checkbox.
    $rowField = $field ?? [];
@endphp

<x-noerd::detail.numbered-row :field="$rowField">
    <div class="flex h-7 items-center">
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
</x-noerd::detail.numbered-row>
