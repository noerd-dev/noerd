<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

uses(Tests\TestCase::class);

it('has direct route for noerd-user-detail', function (): void {
    expect(Route::has('noerd-user.detail'))->toBeTrue();
});

it('has direct route for user-role-detail', function (): void {
    expect(Route::has('user-role.detail'))->toBeTrue();
});

it('has direct route for setup-collection-detail', function (): void {
    expect(Route::has('setup-collection.detail'))->toBeTrue();
});

it('has direct route for setup-language-detail', function (): void {
    expect(Route::has('setup-language.detail'))->toBeTrue();
});
