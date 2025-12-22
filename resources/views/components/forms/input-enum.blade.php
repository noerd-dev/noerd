@props([
    'field' => null,
    'name' => '',
    'label' => '',
    'options' => [],
    'live' => false,
])

@php
    $name = $field['name'] ?? $name;
    $label = $field['label'] ?? $label;
    $options = $field['options'] ?? $options;
    $live = $field['live'] ?? $live;
@endphp

<div>
    <x-noerd::input-label for="{{ $name }}" :value="__($label)"/>
    <select
        @if($live)
            wire:model.live.debounce="{{ $name }}"
        @else
            wire:model="{{ $name }}"
        @endif
        class="w-full border rounded-lg block disabled:shadow-none dark:shadow-none appearance-none text-base sm:text-sm py-2 h-10 leading-[1.375rem] ps-3 pe-3 bg-white dark:bg-white/10 dark:disabled:bg-white/[7%] text-zinc-700 disabled:text-zinc-500 placeholder-zinc-400 disabled:placeholder-zinc-400/70 dark:text-zinc-300 dark:disabled:text-zinc-400 dark:placeholder-zinc-400 dark:disabled:placeholder-zinc-500 shadow-xs border-zinc-200 border-b-zinc-300/80 disabled:border-b-zinc-200 dark:border-white/10 dark:disabled:border-white/5 focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2"
        id="{{ $name }}"
    >
        @foreach($options as $option)
            <option value="{{ $option->name }}">{{ __($option->value) }}</option>
        @endforeach
    </select>
    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2"/>
</div>
