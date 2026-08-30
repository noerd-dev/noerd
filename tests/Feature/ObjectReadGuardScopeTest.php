<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Noerd\Helpers\AccessHelper;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;
use Noerd\Traits\GuardedByObjectPermission;

uses(TestCase::class, RefreshDatabase::class);

/*
 | The opt-in query-level read guard (GuardedByObjectPermission): while the
 | object-read permission denies the model, every query — aggregates
 | included — yields nothing. Proven against a zz fixture model, never a
 | shipped one.
 */

class ZzGuardedRecord extends Model
{
    use GuardedByObjectPermission;

    protected $table = 'zz_guarded_records';

    protected $guarded = [];
}

class ZzUnguardedRecord extends Model
{
    protected $table = 'zz_guarded_records';

    protected $guarded = [];
}

beforeEach(function (): void {
    if (! Schema::hasTable('zz_guarded_records')) {
        Schema::create('zz_guarded_records', function ($table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->integer('amount')->default(0);
            $table->timestamps();
        });
    }

    $this->actingAs(NoerdUser::factory()->adminUser()->create());

    ZzGuardedRecord::create(['name' => 'Zz One', 'amount' => 10]);
    ZzGuardedRecord::create(['name' => 'Zz Two', 'amount' => 32]);
});

it('leaves every query untouched while reading is allowed', function (): void {
    expect(ZzGuardedRecord::query()->count())->toBe(2)
        ->and((int) ZzGuardedRecord::query()->sum('amount'))->toBe(42)
        ->and(ZzGuardedRecord::query()->pluck('name')->all())->toBe(['Zz One', 'Zz Two']);
});

it('yields nothing — aggregates included — while reading is denied', function (): void {
    Gate::define(
        AccessHelper::OBJECT_READ_GATE,
        fn(?NoerdUser $user, string $modelClass): bool => $modelClass !== ZzGuardedRecord::class,
    );

    expect(ZzGuardedRecord::query()->count())->toBe(0)
        ->and((int) ZzGuardedRecord::query()->sum('amount'))->toBe(0)
        ->and(ZzGuardedRecord::query()->get())->toHaveCount(0)
        ->and(ZzGuardedRecord::query()->first())->toBeNull();

    // Only the guarded model is affected — a model without the trait on the
    // same table reads normally (opt-in by design).
    expect(ZzUnguardedRecord::query()->count())->toBe(2);
});

it('lifts the guard through the documented escape hatch', function (): void {
    Gate::define(AccessHelper::OBJECT_READ_GATE, fn(?NoerdUser $user, string $modelClass): bool => false);

    expect(ZzGuardedRecord::query()->count())->toBe(0)
        ->and(ZzGuardedRecord::withoutGlobalScope(ZzGuardedRecord::OBJECT_READ_GUARD_SCOPE)->count())->toBe(2);
});
