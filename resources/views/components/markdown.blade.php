@props(['content' => ''])

@php
    $html = \Illuminate\Support\Str::markdown($content ?? '', [
        'html_input' => 'strip',
        'allow_unsafe_links' => false,
    ]);
@endphp

<div {{ $attributes->merge(['class' => 'rich-text']) }}>
    {!! $html !!}
</div>
