<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Services\RelationTitleResolver;
use Noerd\Support\SchemaColumnCache;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 | The naming-convention fallback of the relation title resolver reads a table
 | directly. That read must never cross the tenant boundary: a foreign tenant's
 | row resolves to the raw id, exactly like a missing row.
 */

beforeEach(function (): void {
    SchemaColumnCache::clear();

    $this->actingAs(NoerdUser::factory()->withExampleTenant()->create());

    Schema::create('zz_scoped_things', function (Blueprint $blueprint): void {
        $blueprint->id();
        $blueprint->unsignedBigInteger('tenant_id')->nullable();
        $blueprint->string('name')->nullable();
    });

    $this->resolver = app(RelationTitleResolver::class);
});

afterEach(function (): void {
    Schema::dropIfExists('zz_scoped_things');
    SchemaColumnCache::clear();
});

it('resolves a convention title for a row of the selected tenant', function (): void {
    $id = DB::table('zz_scoped_things')->insertGetId([
        'tenant_id' => TenantHelper::getSelectedTenantId(),
        'name' => 'Own Tenant Row',
    ]);

    expect($this->resolver->title('zz_scoped_thing_id', $id))->toBe('Own Tenant Row');
});

it('never resolves a convention title across the tenant boundary', function (): void {
    $foreignTenant = Tenant::factory()->create();

    $id = DB::table('zz_scoped_things')->insertGetId([
        'tenant_id' => $foreignTenant->id,
        'name' => 'Foreign Tenant Row',
    ]);

    expect($this->resolver->title('zz_scoped_thing_id', $id))->toBe((string) $id);
});

it('never primes a convention title across the tenant boundary', function (): void {
    $foreignTenant = Tenant::factory()->create();

    $ownId = DB::table('zz_scoped_things')->insertGetId([
        'tenant_id' => TenantHelper::getSelectedTenantId(),
        'name' => 'Own Tenant Row',
    ]);
    $foreignId = DB::table('zz_scoped_things')->insertGetId([
        'tenant_id' => $foreignTenant->id,
        'name' => 'Foreign Tenant Row',
    ]);

    $this->resolver->prime('zz_scoped_thing_id', [$ownId, $foreignId]);

    expect($this->resolver->title('zz_scoped_thing_id', $ownId))->toBe('Own Tenant Row')
        ->and($this->resolver->title('zz_scoped_thing_id', $foreignId))->toBe((string) $foreignId);
});
