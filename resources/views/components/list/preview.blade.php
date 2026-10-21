{{-- The picture of a `preview` column (see ListCellFormatter::preview()): an image, or a
     coloured tile with a heroicon and a short label (e.g. the extension of a file that has
     no thumbnail). Included with $preview (the normalised array) and $previewSize
     (`card` = the top of a grid card, `cell` = a small square in a table cell). --}}
@php
    $previewIsCard = ($previewSize ?? 'cell') === 'card';
@endphp
@if ($preview['url'])
    <img
        src="{{ $preview['url'] }}"
        alt="{{ $preview['label'] }}"
        loading="lazy"
        @class([
            'object-cover',
            'aspect-[4/3] w-full rounded-md bg-gray-50' => $previewIsCard,
            'h-10 w-10 rounded' => ! $previewIsCard,
        ])
    />
@else
    <div
        @class([
            'flex shrink-0 flex-col items-center justify-center',
            'aspect-[4/3] w-full gap-2 rounded-md' => $previewIsCard,
            'h-10 w-10 rounded' => ! $previewIsCard,
            $preview['class'],
        ])
    >
        <x-dynamic-component
            :component="'heroicons::outline.' . $preview['icon']"
            @class(['h-12 w-12' => $previewIsCard, 'h-5 w-5' => ! $previewIsCard])
            stroke-width="1.5"
        />
        @if ($previewIsCard && $preview['label'] !== '')
            <span class="text-xs font-semibold tracking-wide uppercase">{{ $preview['label'] }}</span>
        @endif
    </div>
@endif
