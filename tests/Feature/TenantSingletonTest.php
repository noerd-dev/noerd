<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Noerd\Contracts\TenantSingletonContract;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\Tenant;
use Noerd\Support\TenantContext;
use Noerd\Tests\TestCase;
use Noerd\Traits\BelongsToTenant;
use Noerd\Traits\TenantSingleton;

uses(TestCase::class, RefreshDatabase::class);

/*
 | TenantSingleton: one row per tenant, read through forTenant() — the row or
 | an unsaved instance with the model's defaults — memoized per request and
 | written through updateForTenant(). Proven on a runtime table.
 */

class ZzSingletonSetting extends Model implements TenantSingletonContract
{
    use BelongsToTenant;
    use TenantSingleton;

    protected $table = 'zz_singleton_settings';

    protected $guarded = ['id'];

    public static function tenantDefaults(): array
    {
        return ['color' => 'blue', 'limit' => 10];
    }
}

beforeEach(function (): void {
    if (! Schema::hasTable('zz_singleton_settings')) {
        Schema::create('zz_singleton_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('color')->nullable();
            $table->integer('limit')->default(0);
            $table->timestamps();
        });
    }

    $this->tenant = Tenant::factory()->create();
    $this->other = Tenant::factory()->create();
    TenantHelper::setSelectedTenantId($this->tenant->id);
    ZzSingletonSetting::forgetTenantSingletons();
});

it('answers an unsaved instance with the defaults for a tenant without a row', function (): void {
    $settings = ZzSingletonSetting::forTenant();

    expect($settings->exists)->toBeFalse()
        ->and($settings->tenant_id)->toBe($this->tenant->id)
        ->and($settings->color)->toBe('blue')
        ->and($settings->limit)->toBe(10)
        ->and(ZzSingletonSetting::existsForTenant())->toBeFalse();
});

it('creates the row on the first update and updates it afterwards', function (): void {
    $created = ZzSingletonSetting::updateForTenant(['color' => 'red']);

    expect($created->exists)->toBeTrue()
        ->and($created->limit)->toBe(10)
        ->and(ZzSingletonSetting::query()->count())->toBe(1);

    $updated = ZzSingletonSetting::updateForTenant(['limit' => 42]);

    expect($updated->id)->toBe($created->id)
        ->and($updated->color)->toBe('red')
        ->and($updated->limit)->toBe(42)
        ->and(ZzSingletonSetting::query()->count())->toBe(1);
});

it('memoizes the row per request and drops the memo on save', function (): void {
    $first = ZzSingletonSetting::forTenant();

    expect(ZzSingletonSetting::forTenant())->toBe($first);

    ZzSingletonSetting::updateForTenant(['color' => 'green']);

    expect(ZzSingletonSetting::forTenant())->not->toBe($first)
        ->and(ZzSingletonSetting::forTenant()->color)->toBe('green');
});

it('reads another tenant\'s row past the tenant scope when the tenant is named', function (): void {
    ZzSingletonSetting::updateForTenant(['color' => 'red'], $this->other->id);

    expect(ZzSingletonSetting::forTenant($this->other->id)->color)->toBe('red')
        ->and(ZzSingletonSetting::forTenant($this->other->id)->tenant_id)->toBe($this->other->id)
        ->and(ZzSingletonSetting::forTenant()->exists)->toBeFalse();
});

it('follows the tenant context a job or command entered', function (): void {
    ZzSingletonSetting::updateForTenant(['color' => 'red'], $this->other->id);

    $color = TenantContext::run($this->other->id, fn(): ?string => ZzSingletonSetting::forTenant()->color);

    expect($color)->toBe('red');
});

it('refuses to guess the tenant when none is selected and none is named', function (): void {
    TenantHelper::clear();
    auth('noerd')->logout();

    expect(fn() => ZzSingletonSetting::forTenant())->toThrow(RuntimeException::class, 'needs a tenant');
});
