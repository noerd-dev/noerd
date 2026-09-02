<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Noerd\Tests\TestCase;

uses(TestCase::class);

it('the markdown component escapes embedded HTML while keeping markdown formatting', function (): void {
    $html = Blade::render('<x-noerd::markdown :content="$c" />', [
        'c' => "Hello **bold**\n<script>alert(1)</script>",
    ]);

    expect($html)->toContain('<strong>bold</strong>')
        ->and($html)->not->toContain('<script>')
        ->and($html)->toContain('&lt;script&gt;');
});
