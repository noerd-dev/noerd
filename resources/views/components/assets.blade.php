@php
    $vite = clone app(\Illuminate\Foundation\Vite::class);
@endphp

{{
    $vite->useHotFile(base_path('public/vendor/noerd/hot'))
        ->useBuildDirectory('vendor/noerd')
        ->withEntryPoints(['resources/js/noerd.js'])
}}
