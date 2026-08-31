@props([
    /**
     * Keep the header on ONE non-wrapping row at every breakpoint. Used by the generic
     * list header, whose overflowing controls collapse into the filter drawer instead of
     * wrapping onto further lines. Detail headers stay stacked below `lg`.
     */
    'row' => false,
])

<div @class([
    'mx-auto my-auto w-full items-center font-semibold text-slate-900',
    'lg:flex lg:h-[30px]' => ! $row,
    'flex h-[30px]' => $row,
])>{{ $slot }}</div>
