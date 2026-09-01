{{-- Numbered variant of forms.translatable-textarea: gray numbered row, top-aligned label,
     textarea on the right. The light blue frame marks the field as language-dependent. --}}
@props([
    'field' => null,
    'name' => '',
    'readonly' => false,
    'rows' => 8,
])

@php
    $name = $field['name'] ?? $name;
    $readonly = $field['readonly'] ?? $readonly;
    $rows = $field['rows'] ?? $rows;
    $selectedLang = \Noerd\Models\SetupLanguage::selectedCode();
@endphp

<div wire:key="{{ $name . $selectedLang }}">
    <x-noerd::detail.numbered-row :field="$field" :labelTop="true">
        <textarea
            wire:model="{{ $name . '.' . $selectedLang }}"
            name="{{ $name }}"
            rows="{{ $rows }}"
            class="block w-full appearance-none rounded-none border border-sky-400 bg-sky-50/30 py-1.5 ps-2 pe-2 text-base leading-[1.375rem] text-zinc-700 placeholder-zinc-400 read-only:text-zinc-500 read-only:placeholder-zinc-400/70 focus:border-dotted focus:border-sky-600 focus:ring-0 focus:outline-none sm:text-sm"
            {{ $readonly ? 'readonly' : '' }}
        ></textarea>
    </x-noerd::detail.numbered-row>
</div>
