<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 | Eloquent memoizes every model's guardable COLUMN LIST in a process-global
 | static, filled from the schema of whichever connection first mass-assigns
 | that model. These testbench tests run the package migrations alone, a host
 | application runs its own on top — so a leaked column list makes the other
 | suite silently drop writes to columns it does not know. The TestCase flushes
 | the cache around every test; these two cases guard that boundary.
 */

/** @return array<class-string, array<int, string>> */
function zzGuardableColumns(): array
{
    return Closure::bind(static fn(): array => Model::$guardableColumns, null, Model::class)();
}

it('drops a mass-assigned column that the cached column list does not know', function (): void {
    Closure::bind(static function (): void {
        Model::$guardableColumns[Tenant::class] = ['id', 'name'];
    }, null, Model::class)();

    expect(zzGuardableColumns())->toHaveKey(Tenant::class)
        ->and((new Tenant())->fill(['uuid' => 'zz-poisoned'])->uuid)->toBeNull();
});

it('starts with no column list left over from the previous test', function (): void {
    expect(zzGuardableColumns())->not->toHaveKey(Tenant::class)
        ->and((new Tenant())->fill(['uuid' => 'zz-clean'])->uuid)->toBe('zz-clean');
});
