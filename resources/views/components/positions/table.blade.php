{{-- Position table whose density follows the active theme. `columns` is a
     list of header labels; an entry may be an array to add a width class, and an
     empty label marks the trailing action column:

     :columns="[['label' => 'Quantity', 'class' => 'w-32'], 'Name', '']"

     In a theme that numbers its rows a leading '#' column is prepended, matching
     the number cell that <x-noerd::positions.row> renders. --}}
@props([
    'theme' => 'default',
    'columns' => [],
])

@php
    $themeDefinition = app(\Noerd\Services\ThemeRegistry::class)->get($theme);
@endphp

<table class="{{ $themeDefinition->tableClasses }}">
    <thead class="text-left text-sm font-medium text-gray-700">
        <tr>
            @if ($themeDefinition->numbersRows)
                <th scope="col" class="w-8 text-right text-zinc-400 {{ $themeDefinition->headCellClasses }}">#</th>
            @endif

            @foreach ($columns as $column)
                @php
                    $columnLabel = is_array($column) ? ($column['label'] ?? '') : $column;
                    $columnClass = is_array($column) ? ($column['class'] ?? '') : '';
                @endphp
                <th scope="col" class="{{ trim($columnClass . ' ' . $themeDefinition->headCellClasses) }}">
                    {{ $columnLabel === '' ? '' : __($columnLabel) }}
                </th>
            @endforeach
        </tr>
    </thead>

    {{ $slot }}
</table>
