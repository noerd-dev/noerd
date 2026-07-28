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
    Livewire::addNamespace('header-actions-test', viewPath: __DIR__ . '/fixtures/header-actions');
});

it('mounts a registered list action in the list header with the component name', function (): void {
    app(HeaderActionsRegistry::class)->registerListAction('header-actions-test::probe');

    // noerd-users-list declares $listModel, so the action receives the model class.
    Livewire::test('noerd::noerd-users-list')
        ->assertOk()
        ->assertSee('HA-PROBE:noerd::noerd-users-list/' . NoerdUser::class);
});

it('does not mount list actions in a compact list, whose header is not rendered', function (): void {
    app(HeaderActionsRegistry::class)->registerListAction('header-actions-test::probe');

    Livewire::test('noerd::noerd-users-list', ['compact' => true])
        ->assertOk()
        ->assertDontSee('HA-PROBE:');
});

it('does not mount list actions in a picker list', function (): void {
    app(HeaderActionsRegistry::class)->registerListAction('header-actions-test::probe');

    Livewire::test('noerd::noerd-users-list', ['multiSelect' => true, 'returnsSelection' => true])
        ->assertOk()
        ->assertDontSee('HA-PROBE:');
});

it('mounts a registered detail action in the detail header', function (): void {
    app(HeaderActionsRegistry::class)->registerDetailAction('header-actions-test::probe');

    // noerd-user-detail declares no $detailModel, so the action receives model=null.
    Livewire::test('noerd::noerd-user-detail')
        ->assertOk()
        ->assertSee('HA-PROBE:noerd::noerd-user-detail/no-model');
});

it('does not mount a detail action in a list header', function (): void {
    app(HeaderActionsRegistry::class)->registerDetailAction('header-actions-test::probe');

    // The list header wraps its content in modal-title too — the detail injection
    // there must not fire for a component whose name ends in -list.
    Livewire::test('noerd::noerd-users-list')
        ->assertOk()
        ->assertDontSee('HA-PROBE:');
});

it('does not mount a list action in a detail header', function (): void {
    app(HeaderActionsRegistry::class)->registerListAction('header-actions-test::probe');

    Livewire::test('noerd::noerd-user-detail')
        ->assertOk()
        ->assertDontSee('HA-PROBE:');
});

it('does not mount detail actions in a quick-create detail', function (): void {
    app(HeaderActionsRegistry::class)->registerDetailAction('header-actions-test::probe');

    Livewire::test('noerd::noerd-user-detail', ['quickCreate' => true])
        ->assertOk()
        ->assertDontSee('HA-PROBE:');
});

it('renders normally when no module registered anything', function (): void {
    expect(app(HeaderActionsRegistry::class)->listActions())->toBe([])
        ->and(app(HeaderActionsRegistry::class)->detailActions())->toBe([]);

    Livewire::test('noerd::noerd-users-list')->assertOk()->assertDontSee('HA-PROBE:');
    Livewire::test('noerd::noerd-user-detail')->assertOk()->assertDontSee('HA-PROBE:');
});
