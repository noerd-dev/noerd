@props([
    'field' => null,
    'name' => '',
    'label' => '',
])

@php
    $name = $field['name'] ?? $name;
    $label = $field['label'] ?? $label;
@endphp

<div {{ $attributes->merge(['class' => '']) }}>
    <x-noerd::input-label for="{{ $name }}" :value="__($label)"/>

    <x-noerd::forms.quill
        :field="$name"
        :content="$model[str_replace('model.', '', $name)] ?? ''"
    />

    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2"/>
</div>
