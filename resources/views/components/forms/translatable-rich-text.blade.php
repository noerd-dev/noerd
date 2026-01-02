@props([
    'field' => null,
    'name' => '',
    'label' => '',
])

@php
    $name = $field['name'] ?? $name;
    $label = $field['label'] ?? $label;
@endphp

<div wire:key="{{ $name . (session('selectedLanguage') ?? 'de') }}" {{ $attributes->merge(['class' => '']) }}>
    <x-noerd::input-label for="{{ $name }}" :value="__($label)"/>

    <x-noerd::forms.tiptap
        :field="$name . '.' . (session('selectedLanguage') ?? 'de')"
        :content="$model[str_replace('model.', '', $name)][session('selectedLanguage')] ?? ''"
    />

    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2"/>
</div>
