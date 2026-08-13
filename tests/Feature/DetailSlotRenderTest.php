<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Services\DetailSlotsRegistry;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());

    // Installed modules register themselves at boot, so start from a clean registry:
    // these tests are about the core's slot mechanics, not about whoever is installed.
    app()->instance(DetailSlotsRegistry::class, new DetailSlotsRegistry());
    Livewire::addNamespace('detail-slots-test', viewPath: __DIR__ . '/fixtures/detail-slots');
});

it('mounts a registered slot component in the user detail with the host name and model id', function (): void {
    app(DetailSlotsRegistry::class)->register('user-below-form', 'detail-slots-test::probe');

    $user = NoerdUser::factory()->create();

    Livewire::test('noerd::noerd-user-detail', ['modelId' => $user->id])
        ->assertOk()
        ->assertSee('DS-PROBE:noerd::noerd-user-detail/' . $user->id);
});

it('renders nothing when no component is registered for the slot', function (): void {
    Livewire::test('noerd::noerd-user-detail')
        ->assertOk()
        ->assertDontSee('DS-PROBE');
});

it('renders slot components ordered by their sort value, lower first', function (): void {
    app(DetailSlotsRegistry::class)->register('user-below-form', 'detail-slots-test::probe', sort: 10);
    app(DetailSlotsRegistry::class)->register('user-below-form', 'detail-slots-test::probe-b', sort: 5);

    Livewire::test('noerd::noerd-user-detail')
        ->assertOk()
        ->assertSeeInOrder([
            'DS-PROBE-B:noerd::noerd-user-detail',
            'DS-PROBE:noerd::noerd-user-detail/no-model',
        ]);
});

it('dispatches detailStored after a successful store so slot children can persist', function (): void {
    $admin = auth()->user();
    $tenantId = $admin->adminTenants->first()->id;

    Livewire::test('noerd::noerd-user-detail')
        ->set('sendPasswordResetMail', false)
        ->set('detailData.name', 'Jane Example')
        ->set('detailData.email', 'jane@example.com')
        ->set('possibleTenants.' . $tenantId . '.hasAccess', true)
        ->call('store')
        ->assertHasNoErrors()
        ->assertDispatched('detailStored-noerd::noerd-user-detail');
});

it('does not dispatch detailStored when validation fails', function (): void {
    Livewire::test('noerd::noerd-user-detail')
        ->set('detailData.name', '')
        ->set('detailData.email', 'not-an-email')
        ->call('store')
        ->assertHasErrors()
        ->assertNotDispatched('detailStored-noerd::noerd-user-detail');
});
