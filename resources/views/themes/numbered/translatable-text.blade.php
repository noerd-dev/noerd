{{-- Numbered variant of forms.translatable-text: gray numbered row, right-aligned label,
     control on the right. The light blue frame marks the field as language-dependent. --}}
@props([
    'field' => null,
    'name' => '',
    'type' => 'text',
    'readonly' => false,
    'live' => false,
])

@php
    $name = $field['name'] ?? $name;
    // The YAML field type is `translatableText` — never a valid HTML input type.
    $type = $field['type'] ?? $type;
    $type = in_array($type, ['text', 'email', 'url', 'tel'], true) ? $type : 'text';
    $readonly = $field['readonly'] ?? $readonly;
    $live = $field['live'] ?? $live;
    $selectedLang = session('selectedLanguage') ?? 'de';
@endphp

<div wire:key="{{ $name . $selectedLang }}">
    <x-noerd::detail.numbered-row :field="$field">
        <input
            {{ $readonly ? 'readonly' : '' }}
            autocomplete="off"
            class="block h-9 w-full appearance-none rounded-none border border-sky-400 bg-sky-50/30 py-1.5 ps-2 pe-2 text-base text-zinc-700 placeholder-zinc-400 read-only:text-zinc-500 read-only:placeholder-zinc-400/70 focus:border-dotted focus:border-sky-600 focus:ring-0 focus:outline-none sm:text-sm"
            type="{{ $type }}"
            id="{{ $name }}"
            name="{{ $name }}"
            @if ($live)
                wire:model.live.debounce="{{ $name . '.' . $selectedLang }}"
            @else
                wire:model="{{ $name . '.' . $selectedLang }}"
            @endif
        />
    </x-noerd::detail.numbered-row>
</div>
