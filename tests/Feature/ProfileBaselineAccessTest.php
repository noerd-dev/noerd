<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Enums\Profile;
use Noerd\Helpers\AccessHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdDetail;

uses(TestCase::class, RefreshDatabase::class);

/*
 | The core's profile baseline: with NO gates defined, the per-tenant profile
 | decides — ADMIN/USER (and users without a profile) may do everything,
 | READ_ONLY only read and open apps, MINIMAL nothing. A defined gate decides
 | alone and replaces the baseline.
 */

class ZzProfileBaselineDetail extends Component
{
    use NoerdDetail;

    public $detailModel = Tenant::class;

    public ?string $detailPrimary = 'tenantId';

    public function render(): string
    {
        return '<div></div>';
    }
}

beforeEach(function (): void {
    Livewire::component('zz-profile-baseline-detail', ZzProfileBaselineDetail::class);
});

it('leaves a user without a profile unrestricted', function (): void {
    $this->actingAs(createNoerdUserWithProfile(null));

    expect(AccessHelper::canReadObject(Tenant::class))->toBeTrue()
        ->and(AccessHelper::canWriteObject(Tenant::class))->toBeTrue()
        ->and(AccessHelper::canCreateObject(Tenant::class))->toBeTrue()
        ->and(AccessHelper::canDeleteObject(Tenant::class))->toBeTrue()
        ->and(AccessHelper::canAccessApp('ANY_APP'))->toBeTrue()
        ->and(AccessHelper::canPerformAction('any_action'))->toBeTrue();
});

it('leaves a USER profile unrestricted', function (): void {
    $this->actingAs(createNoerdUserWithProfile(Profile::User));

    expect(AccessHelper::canWriteObject(Tenant::class))->toBeTrue()
        ->and(AccessHelper::canCreateObject(Tenant::class))->toBeTrue()
        ->and(AccessHelper::canDeleteObject(Tenant::class))->toBeTrue()
        ->and(AccessHelper::canAccessApp('ANY_APP'))->toBeTrue()
        ->and(AccessHelper::canPerformAction('any_action'))->toBeTrue();
});

it('restricts a READ_ONLY profile to reading and opening apps', function (): void {
    $this->actingAs(createNoerdUserWithProfile(Profile::ReadOnly));

    expect(AccessHelper::canReadObject(Tenant::class))->toBeTrue()
        ->and(AccessHelper::canAccessApp('ANY_APP'))->toBeTrue()
        ->and(AccessHelper::canWriteObject(Tenant::class))->toBeFalse()
        ->and(AccessHelper::canCreateObject(Tenant::class))->toBeFalse()
        ->and(AccessHelper::canDeleteObject(Tenant::class))->toBeFalse()
        ->and(AccessHelper::canPerformAction('any_action'))->toBeFalse();
});

it('treats an unknown, module-registered profile key like USER', function (): void {
    // Additional profiles come from the ProfileRegistry; their semantics live
    // in the registering module's gates. The core baseline itself must stay
    // permissive for keys it does not know — a missing or foreign assignment
    // never locks an installation out.
    $user = createNoerdUserWithProfile(null);
    $user->tenants()->updateExistingPivot(
        Noerd\Helpers\TenantHelper::getSelectedTenantId(),
        ['profile_key' => 'ZZ_MODULE_PROFILE'],
    );
    $this->actingAs($user->fresh());

    expect(AccessHelper::canReadObject(Tenant::class))->toBeTrue()
        ->and(AccessHelper::canWriteObject(Tenant::class))->toBeTrue()
        ->and(AccessHelper::canAccessApp('ANY_APP'))->toBeTrue()
        ->and(AccessHelper::canPerformAction('any_action'))->toBeTrue();
});

it('lets an ADMIN profile bypass the baseline', function (): void {
    $this->actingAs(createNoerdUserWithProfile(Profile::Admin));

    expect(AccessHelper::canWriteObject(Tenant::class))->toBeTrue()
        ->and(AccessHelper::canDeleteObject(Tenant::class))->toBeTrue()
        ->and(AccessHelper::canPerformAction('any_action'))->toBeTrue();
});

it('lets a super admin bypass the baseline regardless of an assigned profile', function (): void {
    $user = createNoerdUserWithProfile(Profile::ReadOnly);
    $user->forceFill(['super_admin' => true])->save();
    $this->actingAs($user->fresh());

    expect(AccessHelper::canWriteObject(Tenant::class))->toBeTrue()
        ->and(AccessHelper::canPerformAction('any_action'))->toBeTrue();
});

it('never restricts guests through the baseline', function (): void {
    expect(AccessHelper::canReadObject(Tenant::class))->toBeTrue()
        ->and(AccessHelper::canWriteObject(Tenant::class))->toBeTrue()
        ->and(AccessHelper::canAccessApp('ANY_APP'))->toBeTrue()
        ->and(AccessHelper::canPerformAction('any_action'))->toBeTrue();
});

it('lets a defined gate replace the baseline in both directions', function (): void {
    $this->actingAs(createNoerdUserWithProfile(Profile::ReadOnly));

    // A permission layer (noerd-plus) may WIDEN a restrictive baseline …
    Gate::define(AccessHelper::OBJECT_WRITE_GATE, fn(?NoerdUser $user, string $modelClass): bool => true);
    expect(AccessHelper::canWriteObject(Tenant::class))->toBeTrue();

    // … and NARROW a permissive one — the defined gate decides alone.
    Gate::define(AccessHelper::OBJECT_READ_GATE, fn(?NoerdUser $user, string $modelClass): bool => false);
    expect(AccessHelper::canReadObject(Tenant::class))->toBeFalse();
});

it('renders every form field readonly for a READ_ONLY profile without any gate', function (): void {
    $this->actingAs(createNoerdUserWithProfile(Profile::ReadOnly));

    $html = Livewire::test('noerd::write-denied-test')->assertOk()->html();

    preg_match_all('/<input\b[^>]*>/s', $html, $matches);
    $inputs = array_values(array_filter($matches[0], fn(string $tag): bool => str_contains($tag, 'wire:model')));

    expect($inputs)->not->toBe([]);
    foreach ($inputs as $tag) {
        $withoutClasses = preg_replace('/\bclass="[^"]*"/s', '', $tag) ?? $tag;
        expect((bool) preg_match('/\b(readonly|disabled)\b/', $withoutClasses))->toBeTrue();
    }
});

it('makes store() a no-op for a READ_ONLY profile', function (): void {
    $this->actingAs(createNoerdUserWithProfile(Profile::ReadOnly));

    $countBefore = Tenant::query()->count();

    // Create attempt
    Livewire::test('zz-profile-baseline-detail')
        ->set('detailData', validDetailPayload(Tenant::class))
        ->set('detailData.name', 'Baseline Blocked')
        ->call('store')
        ->assertSet('showSuccessIndicator', false);

    expect(Tenant::query()->count())->toBe($countBefore);

    // Update attempt
    $tenant = Tenant::factory()->create(['name' => 'Before']);
    Livewire::test('zz-profile-baseline-detail', ['modelId' => $tenant->id])
        ->set('detailData.name', 'After')
        ->call('store');

    expect($tenant->refresh()->name)->toBe('Before');
});

it('makes delete() a no-op for a READ_ONLY profile', function (): void {
    $this->actingAs(createNoerdUserWithProfile(Profile::ReadOnly));

    $tenant = Tenant::factory()->create();

    Livewire::test('zz-profile-baseline-detail', ['modelId' => $tenant->id])
        ->call('delete');

    expect(Tenant::query()->whereKey($tenant->id)->exists())->toBeTrue();
});
