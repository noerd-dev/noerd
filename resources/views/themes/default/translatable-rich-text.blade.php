{{-- Translatable rich text. The light blue frame marks the field as
     language-dependent — the value shown belongs to the active setup language. --}}
@props([
    'field' => null,
    'name' => '',
    'label' => '',
    'required' => false,
])

@php
    $name = $field['name'] ?? $name;
    $label = $field['label'] ?? $label;
    $readonly = $field['readonly'] ?? false;
    $required = $field['required'] ?? $required;
    $selectedLang = session('selectedLanguage') ?? 'de';

    // Extract the field key from dot notation (e.g., 'summaryData.content' -> 'content', 'model.content' -> 'content')
    $fieldKey = str_contains($name, '.') ? mb_substr($name, mb_strpos($name, '.') + 1) : $name;

    // Get the data array name (e.g., 'summaryData.content' -> 'summaryData', 'model.content' -> 'model')
    $dataArrayName = str_contains($name, '.') ? mb_substr($name, 0, mb_strpos($name, '.')) : 'model';

    // Access the data from the Livewire component
    $dataArray = $this->{$dataArrayName} ?? $model ?? [];
    $contentValue = $dataArray[$fieldKey][$selectedLang] ?? '';
    // Filter out non-scalar values that leak from parent scope via @include
    $attributes = $attributes->filter(fn($value) => is_scalar($value) || null === $value);
@endphp

<div wire:key="{{ $name . $selectedLang }}" {{ $attributes->merge(['class' => '']) }}>
    <x-noerd::input-label for="{{ $name }}" :value="__($label)" :required="$required" />

    <div class="overflow-hidden rounded-lg border border-sky-300 bg-sky-50/30">
        <x-noerd::forms.tiptap :field="$name . '.' . $selectedLang" :content="$contentValue" :readonly="$readonly" />
    </div>

    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2" />
</div>
