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
        class="focus:ring-brand-border block h-7 w-full appearance-none rounded-sm border border-zinc-200 bg-white py-1 ps-2 pe-2 text-base text-zinc-700 placeholder-zinc-400 read-only:text-zinc-500 read-only:placeholder-zinc-400/70 focus:ring-1 focus:outline-none sm:text-sm"
        type="{{ $type }}"
        id="{{ $name }}"
        name="{{ $name }}"
        @if ($live)
            wire:model.live.debounce="{{ $name }}"
        @else
            wire:model="{{ $name }}"
        @endif
        @if ($type === 'date')
            x-init="
                let v = $wire.get('{{ $name }}');
                if (v && v.length > 10) $wire.set('{{ $name }}', v.substring(0, 10), false);
            "
        @elseif ($type === 'time')
            x-init="
                let v = $wire.get('{{ $name }}');
                if (v && v.length > 5) $wire.set('{{ $name }}', v.substring(0, 5), false);
            "
        @endif
    />
</x-noerd::detail.numbered-row>
