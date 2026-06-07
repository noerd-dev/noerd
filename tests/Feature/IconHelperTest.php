<?php

use Noerd\Helpers\IconHelper;
use Tests\TestCase;

uses(TestCase::class);

it('enumerates all outline heroicons sorted', function (): void {
    $icons = IconHelper::heroicons();

    expect($icons)->toBeArray();
    expect(count($icons))->toBeGreaterThan(300);
    expect($icons)->toContain('academic-cap', 'scissors', 'trophy');

    $sorted = $icons;
    sort($sorted);
    expect($icons)->toBe($sorted);
});
