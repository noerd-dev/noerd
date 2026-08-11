{{-- Card wrapper for a position (line item) block: the standard detail block head
     plus a body whose padding follows the active theme. --}}
@props([
    'theme' => 'default',
    'title' => 'Positions',
    'description' => '',
])

@php
    $themeDefinition = app(\Noerd\Services\ThemeRegistry::class)->get($theme);
@endphp

<div class="mt-6 rounded-lg bg-white shadow-xs">
    @include('noerd::components.detail.block-head', [
        'title' => $title === '' ? '' : __($title),
        'description' => $description === '' ? '' : __($description),
    ])

    <div class="{{ $themeDefinition->sectionPadding }}">{{ $slot }}</div>
</div>
