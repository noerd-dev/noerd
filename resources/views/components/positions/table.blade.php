{{-- Position table whose density follows the active theme. `columns` are the resolved
     position columns (`$this->positionColumns()` — entries carrying a `field`): the header
     renders `label` + `width`, and `actions` (default true) appends the empty action header
     matching the row's trash cell.

     In a theme that numbers its rows a leading '#' column is prepended, matching
     the number cell that <x-noerd::positions.row> renders. --}}
@props([
    'theme' => 'default',
    'columns' => [],
    'actions' => true,
])

@php
    $themeDefinition = app(\Noerd\Services\ThemeRegistry::class)->get($theme);

    $headers = collect($columns)
        ->map(fn(array $column): array => ['label' => (string) ($column['label'] ?? ''), 'class' => (string) ($column['width'] ?? '')])
        ->when($actions, fn($headers) => $headers->push(['label' => '', 'class' => '']))
        ->all();
@endphp

<table class="{{ $themeDefinition->tableClasses }}">
    <thead class="text-left text-sm font-medium text-gray-700">
        <tr>
            @if ($themeDefinition->numbersRows)
                <th scope="col" class="w-8 text-right text-zinc-400 {{ $themeDefinition->headCellClasses }}">#</th>
            @endif

            @foreach ($headers as $header)
                <th scope="col" class="{{ trim($header['class'] . ' ' . $themeDefinition->headCellClasses) }}">
                    {{ $header['label'] === '' ? '' : __($header['label']) }}
                </th>
            @endforeach
        </tr>
    </thead>

    {{ $slot }}
</table>
