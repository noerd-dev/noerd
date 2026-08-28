<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Noerd\Tests\TestCase;

uses(TestCase::class);

it('has direct route for noerd-user-detail', function (): void {
    expect(Route::has('noerd.user.detail'))->toBeTrue();
});

it('has direct route for setup-collection-detail', function (): void {
    expect(Route::has('noerd.setup-collection.detail'))->toBeTrue();
});

it('has direct route for setup-language-detail', function (): void {
    expect(Route::has('noerd.setup-language.detail'))->toBeTrue();
});
