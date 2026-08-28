@props(['content' => ''])

@php
    // Escape any raw HTML in the (tenant-editable) content BEFORE markdown, so an
    // embedded <script>/<img onerror=…> renders as literal text instead of markup.
    $processedContent = e($content ?? '');

    // Preserve multiple consecutive empty lines by converting them to <br> tags
    // (our own trusted markup, added after the content was escaped).
    // 3+ newlines = 2+ empty lines, convert extra empty lines to <br>
    $processedContent = preg_replace('/\n{3,}/', "\n\n<br>\n\n", $processedContent);

    $html = \Illuminate\Support\Str::markdown($processedContent, [
        'html_input' => 'allow',
        'allow_unsafe_links' => false,
    ]);
@endphp

<div {{ $attributes->merge(['class' => 'rich-text']) }}>{!! $html !!}</div>
