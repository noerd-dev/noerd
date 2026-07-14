<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Contracts\LayoutOverrideResolver;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;

uses(Tests\TestCase::class, RefreshDatabase::class);

/** Records the (viewType, component) pairs the helper hands the resolver. */
class RecordingLayoutOverrideResolver implements LayoutOverrideResolver
{
    /** @var array<int, string> */
    public static array $seen = [];

    public function apply(string $viewType, string $component, array $config): array
    {
        static::$seen[] = $viewType . '|' . $component;

        return $config;
    }
}

beforeEach(function (): void {
    RecordingLayoutOverrideResolver::$seen = [];
    app()->singleton(LayoutOverrideResolver::class, RecordingLayoutOverrideResolver::class);

    $user = NoerdUser::factory()->create(['super_admin' => true]);
    $tenant = Tenant::factory()->create();
    $user->tenants()->attach($tenant->id);
    TenantHelper::setSelectedTenantId($tenant->id);
    TenantHelper::setSelectedApp('STORE');
    $this->actingAs($user);
});

/**
 * Overrides must be keyed by the config's own identity, not by whichever component
 * happens to render it. Callers pass their namespaced livewire name — the key has to
 * survive that, or an override saved against 'customers-list' is never found again.
 */
it('keys a list override by the canonical component, not the namespaced caller', function (): void {
    StaticConfigHelper::getListConfig('customer::customers-list');

    expect(RecordingLayoutOverrideResolver::$seen)->toContain('list|customers-list')
        ->not->toContain('list|customer::customers-list');
});

it('keys a detail override by the canonical component', function (): void {
    StaticConfigHelper::getComponentFields('customer::customer-detail');

    expect(RecordingLayoutOverrideResolver::$seen)->toContain('detail|customer-detail')
        ->not->toContain('detail|customer::customer-detail');
});

it('leaves an already-canonical component untouched', function (): void {
    StaticConfigHelper::getListConfig('customers-list');

    expect(RecordingLayoutOverrideResolver::$seen)->toContain('list|customers-list');
});

/** Sub-folder configs keep their dots — that is the hub's key format too. */
it('keeps dotted sub-paths in the canonical key', function (): void {
    // The config lives at booking-members/lists/stamp-cards/customer-stamp-cards-list.yml
    TenantHelper::setSelectedApp('BOOKING-MEMBERS');

    StaticConfigHelper::getListConfig('booking-members::stamp-cards.customer-stamp-cards-list');

    expect(RecordingLayoutOverrideResolver::$seen)
        ->toContain('list|stamp-cards.customer-stamp-cards-list');
});
