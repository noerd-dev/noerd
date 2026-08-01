{{-- Bare form control styled by the active theme. Use it for hand-written
     chrome that lives outside the YAML field grid (position tables above all) so
     the control follows the form's theme instead of hardcoding a class string:
     <x-noerd::forms.control :theme="$theme" type="number" wire:model="quantity" wire:change="store" />
     Every wire:*/step/disabled attribute passes through the attribute bag. --}}
@props([
    'theme' => 'default',
    'type' => 'text',
])

@php
    $themeDefinition = app(\Noerd\Services\ThemeRegistry::class)->get($theme);
@endphp

@if ($type === 'select')
    <select {{ $attributes->merge(['class' => $themeDefinition->controlClasses]) }}>{{ $slot }}</select>
@else
    <input type="{{ $type }}" {{ $attributes->merge(['class' => $themeDefinition->controlClasses]) }} />
@endif
