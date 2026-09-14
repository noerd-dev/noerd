{{-- Position table whose density follows the active theme. `columns` accepts two shapes:

     1. Legacy header labels — an entry may be an array to add a width class, and an
        empty label marks the trailing action column:
        :columns="[['label' => 'Quantity', 'class' => 'w-32'], 'Name', '']"

     2. Resolved position columns (`$this->positionColumns(...)`, entries carrying a
        `field`): the header renders `label` + `width`, and `actions` (default true)
        appends the empty action header matching the row's trash cell.

     In a theme that numbers its rows a leading '#' column is prepended, matching
     the number cell that <x-noerd::positions.row> renders. --}}
@props([
    'theme' => 'default',
    'columns' => [],
    'actions' => true,
])

@php
    $themeDefinition = app(\Noerd\Services\ThemeRegistry::class)->get($theme);

    $isResolved = collect($columns)->contains(fn($column) => is_array($column) && array_key_exists('field', $column));

    $headers = collect($columns)
        ->map(fn($column) => match (true) {
            $isResolved && is_array($column) => ['label' => (string) ($column['label'] ?? ''), 'class' => (string) ($column['width'] ?? '')],
            is_array($column) => ['label' => (string) ($column['label'] ?? ''), 'class' => (string) ($column['class'] ?? '')],
            default => ['label' => (string) $column, 'class' => ''],
        })
        ->when($isResolved && $actions, fn($headers) => $headers->push(['label' => '', 'class' => '']))
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
