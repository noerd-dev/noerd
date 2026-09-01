{{-- Compact variant of forms.translatable-text: label sits to the LEFT of the input.
     The light blue frame marks the field as language-dependent. --}}
@props([
    'field' => null,
    'name' => '',
    'label' => '',
    'type' => 'text',
    'readonly' => false,
    'live' => false,
    'required' => false,
])

@php
    $name = $field['name'] ?? $name;
    $label = $field['label'] ?? $label;
    // The YAML field type is `translatableText` — never a valid HTML input type.
    $type = $field['type'] ?? $type;
    $type = in_array($type, ['text', 'email', 'url', 'tel'], true) ? $type : 'text';
    $readonly = $field['readonly'] ?? $readonly;
    $live = $field['live'] ?? $live;
    $required = $field['required'] ?? $required;
    $selectedLang = \Noerd\Models\SetupLanguage::selectedCode();
    // Filter out non-scalar values that leak from parent scope via @include
    $attributes = $attributes->filter(fn($value) => is_scalar($value) || null === $value);
@endphp

<div wire:key="{{ $name . $selectedLang }}" class="flex items-center gap-2">
    <x-noerd::input-label
        for="{{ $name }}"
        :value="__($label)"
        :required="$required"
        :title="__($label)"
        class="w-36 shrink-0 truncate !pb-0"
    />

    <div class="min-w-0 flex-1">
        <input
            {{ $readonly ? 'readonly' : '' }}
            autocomplete="off"
            class="focus:ring-sky-300 block h-7 w-full appearance-none rounded-sm border border-sky-300 bg-sky-50/30 py-1 ps-2 pe-2 text-base text-zinc-700 placeholder-zinc-400 read-only:text-zinc-500 read-only:placeholder-zinc-400/70 focus:ring-1 focus:outline-none sm:text-sm"
            type="{{ $type }}"
            id="{{ $name }}"
            name="{{ $name }}"
            @if ($live)
                wire:model.live.debounce="{{ $name . '.' . $selectedLang }}"
            @else
                wire:model="{{ $name . '.' . $selectedLang }}"
            @endif
        />
        <x-noerd::input-error :messages="$errors->get($name)" class="mt-2" />
    </div>
</div>
