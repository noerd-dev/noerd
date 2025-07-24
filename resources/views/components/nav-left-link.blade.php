@props(['active'])

@php
    $classes = ($active ?? false)
                ? 'bg-gray-800 flex-wrap items-center justify-center w-full text-white group flex gap-x-3 py-3 text-sm leading-6 font-semibold'
                : 'bg-gray-700 flex-wrap items-center justify-center w-full text-gray-400 hover:text-white hover:bg-gray-800 group flex gap-x-3 py-3 text-sm leading-6 font-semibold transition duration-150 ease-in-out';
@endphp

<div class="w-full text-center ">
    <a wire:navigate {{ $attributes->merge(['class' => $classes]) }}">
        <span class="w-full text-center justify-center">
            <center>{{ $slot }}</center>
        </span>

        @if (isset($label))
            <span class="text-xs text-[11px] pt-0.5 font-normal" style="white-space: nowrap;
  overflow: hidden;">
                {{$label}}
            </span>
        @endif
    </a>
</div>
