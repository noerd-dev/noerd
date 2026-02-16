@props([
    'field' => null,
    'name' => '',
    'label' => '',
    'options' => [],
    'live' => false,
    'required' => false,
])

@php
    $name = $field['name'] ?? $name;
    $label = $field['label'] ?? $label;
    $options = $field['options'] ?? $options;
    $live = $field['live'] ?? $live;
    $required = $field['required'] ?? $required;
@endphp

<div>
    <x-noerd::input-label for="{{ $name }}" :value="__($label)" :required="$required"/>
    <select
        @if($live)
            wire:model.live.debounce="{{ $name }}"
        @else
            wire:model="{{ $name }}"
        @endif
        class="w-full border rounded-lg block disabled:shadow-none appearance-none text-base sm:text-sm py-2 h-10 leading-[1.375rem] ps-3 pe-3 bg-white text-zinc-700 disabled:text-zinc-500 placeholder-zinc-400 disabled:placeholder-zinc-400/70 shadow-xs border-zinc-200 border-b-zinc-300/80 disabled:border-b-zinc-200 focus:outline-none focus:ring-2 focus:ring-brand-border focus:ring-offset-2"
        id="{{ $name }}"
    >
        @foreach($options as $option)
            @isset($option['value'])
                <option value="{{ $option['value'] }}">{{ __($option['label']) }}</option>
            @else
                <option>{{ __($option) }}</option>
            @endisset
        @endforeach
    </select>
    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2"/>
</div>
