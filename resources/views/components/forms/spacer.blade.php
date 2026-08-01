@props(['field' => null])

@php
    $spacerClass = app(\Noerd\Services\ThemeRegistry::class)
        ->get($field['theme'] ?? 'default')
        ->spacerClass;
@endphp

{{-- Layout spacer: renders an empty cell the same height as a standard input field (label +
     input in the default layout, just the input height in the denser themes), so it reads as a
     blank line in the grid. The detail block still wraps it in a `col-span-*` cell, reserving the
     slot so the following fields keep their position instead of moving up. --}}
<div aria-hidden="true" class="invisible {{ $spacerClass }}"></div>
