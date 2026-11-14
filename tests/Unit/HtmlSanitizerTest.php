<?php

declare(strict_types=1);

use Noerd\Support\HtmlSanitizer;
use Noerd\Tests\TestCase;

uses(TestCase::class);

/*
 | A link or image source survives the sanitiser only with a scheme of the
 | allow-list (http, https, mailto, tel) or without a scheme at all. Browsers
 | drop tabs, newlines and control characters inside a URL, so a scheme hidden
 | behind them is judged the way the browser would run it.
 */

it('drops a link whose scheme is not allowed', function (string $href): void {
    $html = HtmlSanitizer::sanitize('<a href="' . $href . '">x</a>');

    expect($html)->toBe('<a>x</a>');
})->with([
    'javascript' => ['javascript:alert(1)'],
    'mixed case' => ['JaVaScRiPt:alert(1)'],
    'tab inside the scheme' => ['java&#9;script:alert(1)'],
    'newline inside the scheme' => ['java&#10;script:alert(1)'],
    'leading control character' => ['&#1;javascript:alert(1)'],
    'leading spaces' => ['   javascript:alert(1)'],
    'data' => ['data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg=='],
    'vbscript' => ['vbscript:msgbox(1)'],
]);

it('keeps links with an allowed scheme and relative links', function (string $href): void {
    $html = HtmlSanitizer::sanitize('<a href="' . $href . '">x</a>');

    expect($html)->toContain('href="' . $href . '"');
})->with([
    'https' => ['https://example.test/page'],
    'mixed case https' => ['HTTPS://example.test/page'],
    'mailto' => ['mailto:info@example.test'],
    'tel' => ['tel:+4912345'],
    'absolute path' => ['/imprint'],
    'relative path' => ['docs/page.html'],
    'fragment' => ['#section'],
    'query' => ['?page=2'],
]);

it('drops an image source with a disguised scheme', function (): void {
    expect(HtmlSanitizer::sanitize('<img src="java&#9;script:alert(1)" alt="x">'))
        ->not->toContain('src=');
});

it('drops fetching elements only for documents', function (): void {
    $html = '<p>a <img src="https://example.test/x.png"></p>';

    expect(HtmlSanitizer::sanitize($html))->toContain('<img')
        ->and(HtmlSanitizer::sanitizeForDocument($html))->not->toContain('<img');
});
