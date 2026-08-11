{{-- One position row (a full <tbody>, so it can be the root element of a row
     Livewire component). In a theme that numbers its rows the row is banded and a
     leading number cell is rendered — the `#` column <x-noerd::positions.table>
     adds to the header.

     An optional `details` slot renders as a second full-width row beneath (used
     for a position's rich-text description); pass `colspan` = the number of
     columns declared on the table, the number column is accounted for here. --}}
@props([
    'theme' => 'default',
    'number' => null,
    'colspan' => 0,
])

@php
    $themeDefinition = app(\Noerd\Services\ThemeRegistry::class)->get($theme);
    $detailsColspan = (int) $colspan + ($themeDefinition->numbersRows ? 1 : 0);
@endphp

<tbody>
    <tr class="{{ $themeDefinition->rowClasses }}">
        @if ($themeDefinition->numbersRows)
            <td class="w-8 pr-1 pl-2 text-right align-middle text-sm text-zinc-400 tabular-nums">{{ $number }}</td>
        @endif

        {{ $slot }}
    </tr>

    @isset($details)
        <tr class="{{ $themeDefinition->rowClasses }}">
            <td colspan="{{ $detailsColspan }}" class="pt-3 pb-8">{{ $details }}</td>
        </tr>
    @endisset
</tbody>
