<?php

declare(strict_types=1);

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
 | known at all a query or a create throws. Synthetic fixtures.
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

class ZzTenantFailingJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public function handle(): void
    {
        throw new RuntimeException('zz failing job');
    }
}

/** Runs a failing job synchronously, survives its exception and records its own context. */
class ZzTenantOuterJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public static ?int $tenantAfterInnerFailure = null;

    public static ?int $switchTo = null;

    public function handle(): void
    {
        // The job moves to another tenant before it runs the nested job.
        TenantContext::set(self::$switchTo);

        try {
            ZzTenantFailingJob::dispatchSync();
        } catch (RuntimeException) {
            // The outer job carries on.
        }

        self::$tenantAfterInnerFailure = TenantContext::current();
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

    ZzTenantNote::query()->acrossTenants()->insert([
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
    it('throws for queries and for records without a tenant', function (): void {
        expect(fn() => zzTenantTitles())->toThrow(MissingTenantContextException::class)
            ->and(fn() => ZzTenantNote::query()->create(['title' => 'Orphan']))->toThrow(MissingTenantContextException::class);

        // An explicit tenant_id is no missing context.
        $note = ZzTenantNote::query()->create(['tenant_id' => $this->tenantA->id, 'title' => 'Explicit']);
        expect($note->exists)->toBeTrue();
    });

    it('reads every tenant only when the code says so', function (): void {
        expect(ZzTenantNote::query()->acrossTenants()->orderBy('title')->pluck('title')->all())->toBe(['A', 'B'])
            ->and(TenantContext::system(fn() => zzTenantTitles()))->toBe(['A', 'B']);
    });

    it('sets up a new tenant in that tenant', function (): void {
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

    it('run after the response in the tenant of the request', function (): void {
        Illuminate\Support\Facades\Route::get('/zz-tenant-after-response', function () {
            TenantContext::set(7);
            ZzTenantProbeJob::dispatchAfterResponse();

            return 'ok';
        });

        $this->get('/zz-tenant-after-response')->assertOk();

        expect(ZzTenantProbeJob::$seenTenant)->toBe(7)
            ->and(TenantContext::current())->toBeNull();
    });

    it('keep the dispatching job in its tenant when a nested sync job fails', function (): void {
        ZzTenantOuterJob::$tenantAfterInnerFailure = null;
        ZzTenantOuterJob::$switchTo = $this->tenantB->id;

        TenantContext::run($this->tenantA->id, function (): void {
            ZzTenantOuterJob::dispatch();
        });

        expect(ZzTenantOuterJob::$tenantAfterInnerFailure)->toBe($this->tenantB->id);
    });

    it('hand the dispatcher its own context back', function (): void {
        TenantContext::run($this->tenantA->id, function (): void {
            ZzTenantProbeJob::dispatch();

            expect(TenantContext::current())->toBe($this->tenantA->id);
        });

        expect(TenantContext::current())->toBeNull();
    });
});
