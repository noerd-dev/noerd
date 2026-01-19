@props(['content' => ''])

@php
    $processedContent = $content ?? '';

    // Preserve multiple consecutive empty lines by converting them to <br> tags
    // 3+ newlines = 2+ empty lines, convert extra empty lines to <br>
    $processedContent = preg_replace('/\n{3,}/', "\n\n<br>\n\n", $processedContent);

    $html = \Illuminate\Support\Str::markdown($processedContent, [
        'html_input' => 'allow',
        'allow_unsafe_links' => false,
    ]);
@endphp

<div {{ $attributes->merge(['class' => 'rich-text']) }}>
    {!! $html !!}
</div>
