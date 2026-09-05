<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Noerd\Tests\TestCase;

uses(TestCase::class);

it('the rich text component renders editor HTML as markup', function (): void {
    $html = Blade::render('<x-noerd::rich-text :content="$c" />', [
        'c' => '<h2>Title</h2><p>Test</p><ul><li>One</li></ul>',
    ]);

    expect($html)->toContain('<h2>Title</h2>')
        ->and($html)->toContain('<p>Test</p>')
        ->and($html)->toContain('<li>One</li>')
        ->and($html)->not->toContain('&lt;p&gt;');
});

it('the rich text component drops scripts and event handlers', function (): void {
    $html = Blade::render('<x-noerd::rich-text :content="$c" />', [
        'c' => '<p onclick="alert(1)">Hi</p><script>alert(1)</script><img src="x" onerror="alert(1)">',
    ]);

    expect($html)->toContain('<p>Hi</p>')
        ->and($html)->not->toContain('<script')
        ->and($html)->not->toContain('alert(1)')
        ->and($html)->not->toContain('onclick')
        ->and($html)->not->toContain('onerror');
});

it('the rich text component drops unsafe link schemes but keeps safe ones', function (): void {
    $html = Blade::render('<x-noerd::rich-text :content="$c" />', [
        'c' => '<p><a href="javascript:alert(1)">bad</a> <a href="https://example.com">good</a></p>',
    ]);

    expect($html)->not->toContain('javascript:')
        ->and($html)->toContain('href="https://example.com"')
        ->and($html)->toContain('bad');
});

it('the rich text component keeps the text of disallowed tags', function (): void {
    $html = Blade::render('<x-noerd::rich-text :content="$c" />', [
        'c' => '<div class="x"><p>Kept</p></div>',
    ]);

    expect($html)->toContain('<p>Kept</p>')
        ->and($html)->not->toContain('<div class="x"');
});

it('the rich text component renders plain text unchanged', function (): void {
    $html = Blade::render('<x-noerd::rich-text :content="$c" />', [
        'c' => 'Dies ist dein Shop.',
    ]);

    expect($html)->toContain('Dies ist dein Shop.');
});
