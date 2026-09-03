<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Noerd\Tests\TestCase;

uses(TestCase::class);

/*
 | The console surface is public API: a renamed command breaks every script and
 | doc that calls the old name, so the shipped names are pinned here. Renames
 | ship WITHOUT an alias — the old name must be gone, not silently kept alive.
 */

function zzRegisteredCommandNames(): array
{
    return array_keys(app(Kernel::class)->all());
}

it('ships the make/promote command names', function (string $name): void {
    expect(zzRegisteredCommandNames())->toContain($name);
})->with([
    'noerd:make-module',
    'noerd:make-theme',
    'noerd:make-app',
    'noerd:make-resource',
    'noerd:make-admin-user',
    'noerd:promote-admin',
]);

it('no longer registers the replaced command names', function (string $name): void {
    expect(zzRegisteredCommandNames())->not->toContain($name);
})->with([
    'noerd:module',
    'noerd:theme',
    'noerd:make-admin',
]);
