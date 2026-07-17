<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Services\HeaderActionsRegistry;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());

    // Installed modules register themselves at boot, so start from a clean registry:
    // these tests are about the core's header slots, not about whoever is installed.
    app()->instance(HeaderActionsRegistry::class, new HeaderActionsRegistry());
    view()->addNamespace('header-actions-test', __DIR__.'/fixtures/header-actions');
});

it('includes a registered partial in the list header with the component and view type', function (): void {
    app(HeaderActionsRegistry::class)->register('header-actions-test::probe');

    Livewire::test('noerd::noerd-users-list')
        ->assertOk()
        ->assertSee('HA-PROBE:noerd::noerd-users-list/list');
});

it('does not include the partial in a compact list, whose header is not rendered', function (): void {
    app(HeaderActionsRegistry::class)->register('header-actions-test::probe');

    Livewire::test('noerd::noerd-users-list', ['compact' => true])
        ->assertOk()
        ->assertDontSee('HA-PROBE:');
});

it('does not include the partial in a picker list', function (): void {
    app(HeaderActionsRegistry::class)->register('header-actions-test::probe');

    Livewire::test('noerd::noerd-users-list', ['multiSelect' => true, 'returnsSelection' => true])
        ->assertOk()
        ->assertDontSee('HA-PROBE:');
});

it('includes a registered partial in the detail header with the detail view type', function (): void {
    app(HeaderActionsRegistry::class)->register('header-actions-test::probe');

    Livewire::test('noerd::noerd-user-detail')
        ->assertOk()
        ->assertSee('HA-PROBE:noerd::noerd-user-detail/detail');
});

it('never marks a list header as a detail one', function (): void {
    app(HeaderActionsRegistry::class)->register('header-actions-test::probe');

    // The list header wraps its content in modal-title too — the detail injection
    // there must not fire for a component whose name ends in -list.
    Livewire::test('noerd::noerd-users-list')
        ->assertOk()
        ->assertDontSee('HA-PROBE:noerd::noerd-users-list/detail');
});

it('does not include the partial in a quick-create detail', function (): void {
    app(HeaderActionsRegistry::class)->register('header-actions-test::probe');

    Livewire::test('noerd::noerd-user-detail', ['quickCreate' => true])
        ->assertOk()
        ->assertDontSee('HA-PROBE:');
});

it('renders normally when no module registered anything', function (): void {
    expect(app(HeaderActionsRegistry::class)->all())->toBe([]);

    Livewire::test('noerd::noerd-users-list')->assertOk()->assertDontSee('HA-PROBE:');
    Livewire::test('noerd::noerd-user-detail')->assertOk()->assertDontSee('HA-PROBE:');
});
