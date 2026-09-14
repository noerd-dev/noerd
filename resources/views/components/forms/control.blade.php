{{-- Bare form control styled by the active theme. Use it for hand-written
     chrome that lives outside the YAML field grid (position tables above all) so
     the control follows the form's theme instead of hardcoding a class string:
     <x-noerd::forms.control :theme="$theme" type="number" wire:model="quantity" wire:change="store" />
     Every wire:*/step/disabled attribute passes through the attribute bag.
     `type="checkbox"` renders a checkbox vertically centred in a box of the
     theme's control height. --}}
@props([
    'theme' => 'default',
    'type' => 'text',
])

@php
    $themeDefinition = app(\Noerd\Services\ThemeRegistry::class)->get($theme);
@endphp

@if ($type === 'select')
    <select {{ $attributes->merge(['class' => $themeDefinition->controlClasses]) }}>
        {{ $slot }}
    </select>
@elseif ($type === 'checkbox')
    @php
        preg_match('/(?:^|\s)(h-\S+)/', $themeDefinition->controlClasses, $heightMatch);
    @endphp
    <div class="flex items-center {{ $heightMatch[1] ?? 'h-10' }}">
        <input type="checkbox" {{ $attributes->merge(['class' => 'text-brand-primary focus:ring-brand-border h-4 w-4 rounded-sm border border-gray-300 disabled:opacity-50']) }} />
    </div>
@else
    <input type="{{ $type }}" {{ $attributes->merge(['class' => $themeDefinition->controlClasses]) }} />
@endif
