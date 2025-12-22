@props([
    'field' => null,
    'name' => '',
    'label' => '',
    'multiple' => false,
    'accept' => '',
    'live' => false,
])

@php
    $name = $field['name'] ?? $name;
    $label = $field['label'] ?? $label;
    $multiple = $field['multiple'] ?? $multiple;
    $accept = $field['accept'] ?? $accept;
    $live = $field['live'] ?? $live;
@endphp

<div>
    @if($label)
        <x-noerd::input-label for="{{ $name }}" :value="__($label)"/>
    @endif
    <input
        type="file"
        id="{{ $name }}"
        @if($live)
            wire:model.live="{{ $name }}"
        @else
            wire:model="{{ $name }}"
        @endif
        @if($multiple) multiple @endif
        @if($accept) accept="{{ $accept }}" @endif
        class="w-full border rounded-lg block text-base sm:text-sm py-2 h-10 ps-3 pe-3
               bg-white dark:bg-white/10 text-zinc-700 dark:text-zinc-300
               border-zinc-200 dark:border-white/10
               file:mr-4 file:py-1 file:px-4 file:rounded file:border-0
               file:text-sm file:font-medium file:bg-zinc-100 file:text-zinc-700
               dark:file:bg-white/20 dark:file:text-zinc-300
               focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2"
    >
    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2"/>
</div>
