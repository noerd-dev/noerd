<?php

declare(strict_types=1);

use Noerd\Support\SafeMarkdown;
use Noerd\Tests\TestCase;

uses(TestCase::class);

/**
 * league/commonmark allows raw HTML by default, so `Str::markdown()` without
 * options passes an injected tag straight through. Everywhere the result is
 * printed with `{!! !!}` that is an HTML injection sink — and inside a PDF it
 * is worse than defacement, because dompdf resolves remote sources from the
 * SERVER, reaching hosts the public internet cannot.
 */
it('escapes raw html instead of passing it through', function (string $input, string $mustNotContain): void {
    $rendered = SafeMarkdown::render($input);

    expect($rendered)->not->toContain($mustNotContain);
})->with([
    'server-side request forge' => ['<img src="http://169.254.169.254/latest/meta-data/">', '<img'],
    'script tag' => ['<script>alert(1)</script>', '<script'],
    'style import' => ['<style>@import url("http://evil.test/x.css");</style>', '<style'],
    'iframe' => ['<iframe src="http://evil.test"></iframe>', '<iframe'],
    // The attribute name survives as inert TEXT — what must not survive is the
    // tag that would carry it.
    'event handler on allowed tag' => ['<b onmouseover="alert(1)">x</b>', '<b onmouseover'],
]);

it('refuses unsafe link schemes', function (): void {
    expect(SafeMarkdown::render('[click](javascript:alert(1))'))
        ->not->toContain('javascript:');
});

it('still renders ordinary markdown', function (): void {
    expect(SafeMarkdown::render("**bold** and *italic*"))
        ->toContain('<strong>bold</strong>')
        ->toContain('<em>italic</em>');
});

it('returns an empty string for no content', function (): void {
    expect(SafeMarkdown::render(null))->toBe('')
        ->and(SafeMarkdown::render(''))->toBe('');
});

// The two safety options are the point of the class, so a caller must not be
// able to hand them back to the unsafe defaults.
it('ignores attempts to re-enable raw html through the options', function (): void {
    expect(SafeMarkdown::render('<script>alert(1)</script>', ['html_input' => 'allow']))
        ->not->toContain('<script');
});

// Markdown's own image syntax is no raw HTML, so render() keeps it — for a
// server-rendered document that is a remote fetch by the renderer.
it('drops remote images for documents only', function (): void {
    $markdown = 'text ![x](http://169.254.169.254/latest/meta-data/)';

    expect(SafeMarkdown::render($markdown))->toContain('<img')
        ->and(SafeMarkdown::renderForDocument($markdown))->not->toContain('<img')
        ->and(SafeMarkdown::renderForDocument($markdown))->toContain('text')
        ->and(SafeMarkdown::renderForDocument('<script>alert(1)</script>'))->not->toContain('<script')
        ->and(SafeMarkdown::renderForDocument(null))->toBe('');
});
