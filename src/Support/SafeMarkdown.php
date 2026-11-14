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
 * printed with `{!! !!}` — a mail body, an answer in the browser — that is an
 * HTML injection sink. render() escapes raw HTML and refuses unsafe link
 * schemes.
 *
 * render() still lets Markdown's OWN image syntax through: `![x](http://…)`
 * becomes an `<img>` with a remote source. In a browser that is the browser's
 * fetch; in a server-rendered document (dompdf) the RENDERER resolves it from
 * the application host — a request forge into the internal network. Anything
 * rendered into a PDF or another server-side document therefore goes through
 * renderForDocument(), which additionally drops every element that fetches a
 * URL (HtmlSanitizer::sanitizeForDocument()).
 *
 * Use this for anything a user or tenant typed. Use Str::markdown() directly
 * only for text the application itself authored.
 */
final class SafeMarkdown
{
    /**
     * Markdown for web output (browser, mail).
     *
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

    /**
     * Markdown for a server-rendered document (PDF): render() plus
     * HtmlSanitizer::sanitizeForDocument(), so no remote image or other
     * fetching element reaches the renderer.
     *
     * @param  array<string, mixed>  $options  Merged over the safe defaults; the
     *                                         two safety keys cannot be overridden.
     */
    public static function renderForDocument(?string $content, array $options = []): string
    {
        return HtmlSanitizer::sanitizeForDocument(self::render($content, $options));
    }
}
