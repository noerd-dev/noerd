{{-- Numbered variant of forms.input: gray numbered row, right-aligned label, control on the right. --}}
@props([
    'field' => null,
    'name' => '',
    'type' => 'text',
    'readonly' => false,
    'live' => false,
])

@php
    $name = $field['name'] ?? $name;
    $type = $field['type'] ?? $type;
    $readonly = $field['readonly'] ?? $readonly;
    $live = $field['live'] ?? $live;
@endphp

<x-noerd::detail.numbered-row :field="$field">
    <input
        {{ $readonly ? 'readonly' : '' }}
        autocomplete="off"
        class="w-full border border-zinc-200 rounded-sm block appearance-none text-base sm:text-sm py-1 h-7 ps-2 pe-2 bg-white text-zinc-700 read-only:text-zinc-500 placeholder-zinc-400 read-only:placeholder-zinc-400/70 focus:outline-none focus:ring-1 focus:ring-brand-border"
        type="{{ $type }}"
        id="{{ $name }}"
        name="{{ $name }}"
        @if($live)
            wire:model.live.debounce="{{ $name }}"
        @else
            wire:model="{{ $name }}"
        @endif
        @if($type === 'date')
            x-init="
                let v = $wire.get('{{ $name }}');
                if (v && v.length > 10) $wire.set('{{ $name }}', v.substring(0, 10), false);
            "
        @elseif($type === 'time')
            x-init="
                let v = $wire.get('{{ $name }}');
                if (v && v.length > 5) $wire.set('{{ $name }}', v.substring(0, 5), false);
            "
        @endif
    >
</x-noerd::detail.numbered-row>
