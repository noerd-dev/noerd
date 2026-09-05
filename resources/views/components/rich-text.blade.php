@props(['content' => ''])

@php
    // The rich text editor (x-noerd::forms.tiptap) stores real HTML, so the content is
    // rendered as markup — but only after an allow-list pass that drops <script>, event
    // handlers, javascript: URLs and everything else outside the safe subset, because
    // the content is tenant-editable.
    $html = \Noerd\Support\HtmlSanitizer::sanitize($content);
@endphp

<div {{ $attributes->merge(['class' => 'rich-text']) }}>{!! $html !!}</div>
