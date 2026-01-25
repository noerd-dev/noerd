@php
    $manifestPath = public_path('vendor/noerd/manifest.json');
    $hotFilePath = public_path('vendor/noerd/hot');
@endphp

@if(file_exists($manifestPath) || file_exists($hotFilePath))
    @php
        $vite = clone app(\Illuminate\Foundation\Vite::class);
    @endphp

    {{
        $vite->useHotFile($hotFilePath)
            ->useBuildDirectory('vendor/noerd')
            ->withEntryPoints(['resources/js/noerd.js'])
    }}
@endif
