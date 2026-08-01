{{-- One cell of a position row; the padding follows the active theme. --}}
@props([
    'theme' => 'default',
    'width' => 'w-32',
])

@php
    $themeDefinition = app(\Noerd\Services\ThemeRegistry::class)->get($theme);
@endphp

<td {{ $attributes->merge(['class' => trim($width . ' ' . $themeDefinition->cellClasses)]) }}>
    {{ $slot }}
</td>
