<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Support\Str;

/**
 * Markdown rendering for values a user typed.
 *
 * `Str::markdown()` without options uses league/commonmark's defaults, and
 * those are `html_input => allow` and `allow_unsafe_links => true`: raw HTML in
 * the source passes straight through to the output. Wherever the result is then
 * printed with `{!! !!}` — an invoice position description in a PDF, a mail
 * body, an answer on a printed card — that is an HTML injection sink.
 *
 * In a PDF it is worse than defacement: dompdf resolves `<img src="http://…">`
 * server-side, so an injected tag turns the renderer into a request forge
 * inside the application network.
 *
 * Use this for anything a user or tenant typed. Use Str::markdown() directly
 * only for text the application itself authored.
 */
final class SafeMarkdown
{
    /**
     * @param  array<string, mixed>  $options  Merged over the safe defaults; the
     *                                         two safety keys cannot be overridden.
     */
    public static function render(?string $content, array $options = []): string
    {
        if ($content === null || $content === '') {
            return '';
        }

        return Str::markdown($content, [
            ...$options,
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }
}
