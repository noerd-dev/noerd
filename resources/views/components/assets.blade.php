@php
    use Illuminate\Foundation\Vite;
    $vite = clone app(Vite::class);
@endphp

{{
    $vite->useHotFile(base_path('public/vendor/noerd/hot'))
        ->useBuildDirectory('vendor/noerd')
        ->withEntryPoints([
            'resources/js/noerd.js',
        ])
}}
