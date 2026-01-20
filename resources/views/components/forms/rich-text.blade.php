@props([
    'field' => null,
    'name' => '',
    'label' => '',
])

@php
    $name = $field['name'] ?? $name;
    $label = $field['label'] ?? $label;

    // Extract the field key from dot notation (e.g., 'summaryData.content' -> 'content', 'model.content' -> 'content')
    $fieldKey = str_contains($name, '.') ? substr($name, strpos($name, '.') + 1) : $name;

    // Get the data array name (e.g., 'summaryData.content' -> 'summaryData', 'model.content' -> 'model')
    $dataArrayName = str_contains($name, '.') ? substr($name, 0, strpos($name, '.')) : 'model';

    // Access the data from the Livewire component
    $dataArray = $this->$dataArrayName ?? $model ?? [];
    $contentValue = $dataArray[$fieldKey] ?? '';
@endphp

<div {{ $attributes->merge(['class' => '']) }}>
    <x-noerd::input-label for="{{ $name }}" :value="__($label)"/>

    <x-noerd::forms.tiptap
        :field="$name"
        :content="$contentValue"
    />

    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2"/>
</div>
