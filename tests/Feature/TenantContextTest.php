<?php

declare(strict_types=1);

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Noerd\Exceptions\MissingTenantContextException;
use Noerd\Models\NoerdUser;
use Noerd\Models\SetupCollectionEntry;
use Noerd\Models\SetupLanguage;
use Noerd\Models\Tenant;
use Noerd\Support\TenantContext;
use Noerd\Tests\TestCase;
use Noerd\Traits\BelongsToTenant;

uses(TestCase::class, RefreshDatabase::class);

/*
 | A tenant is known without a signed-in user once a TenantContext is set:
 | queries are scoped to it, queued jobs inherit it, and where no tenant is
 | known at all `noerd.tenancy.missing_context` decides. Synthetic fixtures.
 */

class ZzTenantNote extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'zz_tenant_notes';

    protected $guarded = [];
}

class ZzTenantProbeJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public static ?int $seenTenant = null;

    public function handle(): void
    {
        self::$seenTenant = TenantContext::current();
    }
}

beforeEach(function (): void {
    Schema::create('zz_tenant_notes', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('tenant_id')->nullable();
        $table->string('title')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

    $this->tenantA = Tenant::factory()->create();
    $this->tenantB = Tenant::factory()->create();

    ZzTenantNote::query()->insert([
        ['tenant_id' => $this->tenantA->id, 'title' => 'A'],
        ['tenant_id' => $this->tenantB->id, 'title' => 'B'],
    ]);
});

function zzTenantTitles(): array
{
    return ZzTenantNote::query()->orderBy('title')->pluck('title')->all();
}

describe('context', function (): void {
    it('scopes queries to the context without a signed-in user', function (): void {
        expect(TenantContext::run($this->tenantA->id, fn() => zzTenantTitles()))->toBe(['A'])
            ->and(TenantContext::current())->toBeNull();
    });

    it('stamps new records with the context tenant', function (): void {
        $note = TenantContext::run($this->tenantB->id, fn() => ZzTenantNote::query()->create(['title' => 'New']));

        expect($note->tenant_id)->toBe($this->tenantB->id);
    });

    it('lifts the tenant filter in system mode, also for a signed-in user', function (): void {
        $this->actingAs(NoerdUser::factory()->adminUser()->create());

        expect(TenantContext::system(fn() => zzTenantTitles()))->toBe(['A', 'B']);
    });

    it('queries one tenant or all of them and keeps soft deletes applied', function (): void {
        ZzTenantNote::query()->acrossTenants()->where('title', 'B')->first()->delete();

        TenantContext::run($this->tenantA->id, function (): void {
            expect(ZzTenantNote::query()->ofTenant($this->tenantB->id)->count())->toBe(0)
                ->and(ZzTenantNote::query()->acrossTenants()->pluck('title')->all())->toBe(['A'])
                ->and(ZzTenantNote::withTrashed()->ofTenant($this->tenantB->id)->count())->toBe(1);
        });
    });
});

it('ends with the request', function (): void {
    Illuminate\Support\Facades\Route::get('/zz-tenant-context', function () {
        TenantContext::set(7);

        return 'ok';
    });

    $this->get('/zz-tenant-context')->assertOk();

    expect(TenantContext::current())->toBeNull();
});

describe('missing context', function (): void {
    it('stays unscoped by default', function (): void {
        expect(zzTenantTitles())->toBe(['A', 'B']);
    });

    it('logs the call site once per model in audit mode', function (): void {
        config(['noerd.tenancy.missing_context' => 'audit']);
        Log::spy();

        expect(zzTenantTitles())->toBe(['A', 'B']);
        zzTenantTitles();

        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn(string $message, array $context): bool => $context['model'] === ZzTenantNote::class
                && str_contains((string) $context['caller'], 'TenantContextTest.php'),
        );
    });

    it('returns no rows and reports in empty mode', function (): void {
        config(['noerd.tenancy.missing_context' => 'empty']);
        Exceptions::fake();

        expect(zzTenantTitles())->toBe([]);

        Exceptions::assertReported(MissingTenantContextException::class);
    });

    it('throws in throw mode, for queries and for records without a tenant', function (): void {
        config(['noerd.tenancy.missing_context' => 'throw']);

        expect(fn() => zzTenantTitles())->toThrow(MissingTenantContextException::class)
            ->and(fn() => ZzTenantNote::query()->create(['title' => 'Orphan']))->toThrow(MissingTenantContextException::class);

        // An explicit tenant_id is no missing context.
        $note = ZzTenantNote::query()->create(['tenant_id' => $this->tenantA->id, 'title' => 'Explicit']);
        expect($note->exists)->toBeTrue();
    });

    it('sets up a new tenant in that tenant, also in throw mode', function (): void {
        config(['noerd.tenancy.missing_context' => 'throw']);

        $tenant = Tenant::factory()->create();

        expect(SetupLanguage::query()->ofTenant($tenant->id)->count())->toBeGreaterThan(0)
            ->and(SetupCollectionEntry::query()->ofTenant($tenant->id)->count())->toBeGreaterThan(0)
            ->and(TenantContext::current())->toBeNull();
    });
});

describe('queued jobs', function (): void {
    beforeEach(function (): void {
        config(['queue.default' => 'sync']);
        ZzTenantProbeJob::$seenTenant = null;
    });

    it('run in the tenant of the code that dispatched them', function (): void {
        // A PendingDispatch dispatches on destruction — inside the closure, not after it.
        TenantContext::run($this->tenantB->id, function (): void {
            ZzTenantProbeJob::dispatch();
        });

        expect(ZzTenantProbeJob::$seenTenant)->toBe($this->tenantB->id);
    });

    it('carry the signed-in user\'s tenant', function (): void {
        $user = NoerdUser::factory()->adminUser()->create();
        $this->actingAs($user);

        ZzTenantProbeJob::dispatch();

        expect(ZzTenantProbeJob::$seenTenant)->toBe($user->selected_tenant_id);
    });

    it('hand the dispatcher its own context back', function (): void {
        TenantContext::run($this->tenantA->id, function (): void {
            ZzTenantProbeJob::dispatch();

            expect(TenantContext::current())->toBe($this->tenantA->id);
        });

        expect(TenantContext::current())->toBeNull();
    });
});
