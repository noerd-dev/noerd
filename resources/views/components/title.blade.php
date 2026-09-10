@props([
    /**
     * Keep the header on ONE non-wrapping row at every breakpoint. Used by the generic
     * list header, whose title row holds only the title and the buttons — search and
     * filters live on their own row below it. Detail headers stay stacked below `lg`.
     */
    'row' => false,
])

<div @class([
    'mx-auto my-auto w-full items-center font-semibold text-slate-900',
    'lg:flex lg:h-[30px]' => ! $row,
    'flex h-[30px]' => $row,
])>{{ $slot }}</div>
