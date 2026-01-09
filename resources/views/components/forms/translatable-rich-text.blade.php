@props([
    'field' => null,
    'name' => '',
    'label' => '',
])

@php
    $name = $field['name'] ?? $name;
    $label = $field['label'] ?? $label;
    $baseKey = str_replace('model.', '', $name);
    $selectedLang = session('selectedLanguage') ?? 'de';
    // Access model via $this (Livewire component) since $model may not be in scope
    $modelData = $this->model ?? $model ?? [];
    $contentValue = $modelData[$baseKey][$selectedLang] ?? '';
@endphp

<div wire:key="{{ $name . $selectedLang }}" {{ $attributes->merge(['class' => '']) }}>
    <x-noerd::input-label for="{{ $name }}" :value="__($label)"/>

    <x-noerd::forms.tiptap
        :field="$name . '.' . $selectedLang"
        :content="$contentValue"
    />

    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2"/>
</div>
