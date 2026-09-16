<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Services\HeaderActionsRegistry;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

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

it('mounts a registered detail action in the head row of the first form block, not in the header', function (): void {
    app(HeaderActionsRegistry::class)->registerDetailAction('header-actions-test::probe');

    $html = Livewire::test('noerd-test::header-actions-detail')
        ->assertOk()
        // Header first, then the block title, then the probe inside the block head.
        ->assertSeeInOrder(['Zz Header Actions Header', 'Zz Header Actions Block', 'HA-PROBE:noerd-test::header-actions-detail/' . NoerdUser::class])
        ->html();

    expect(mb_strpos($html, 'HA-PROBE:'))->toBeGreaterThan(mb_strpos($html, 'Zz Header Actions Block'));
});

it('mounts the detail action exactly once for a tabbed layout with a nested block', function (): void {
    app(HeaderActionsRegistry::class)->registerDetailAction('header-actions-test::probe');

    // Tab 2 renders a second block, the nested `type: block` a third — the actions
    // belong to the first block only (Livewire keys children per parent).
    $html = Livewire::test('noerd-test::header-actions-detail')->assertOk()->html();

    expect(mb_substr_count($html, 'HA-PROBE:'))->toBe(1);
});

it('mounts the detail action once on a detail that includes the form block directly', function (): void {
    app(HeaderActionsRegistry::class)->registerDetailAction('header-actions-test::probe');

    // The hand-built shape: two direct block includes, no tab-content.
    $html = Livewire::test('noerd-test::header-actions-direct-detail')
        ->assertOk()
        ->assertSeeInOrder(['Zz Direct Block', 'HA-PROBE:noerd-test::header-actions-direct-detail/', 'Zz Second Direct Block'])
        ->html();

    expect(mb_substr_count($html, 'HA-PROBE:'))->toBe(1);
});

it('mounts the detail action inside an embedded detail', function (): void {
    app(HeaderActionsRegistry::class)->registerDetailAction('header-actions-test::probe');

    // Embedded, the detail renders chrome-less — its form block still carries the actions.
    Livewire::test('noerd-test::header-actions-detail', ['embedded' => true])
        ->assertOk()
        ->assertSee('HA-PROBE:noerd-test::header-actions-detail/' . NoerdUser::class)
        ->assertDontSee('Zz Header Actions Header');
});

it('mounts no action of its own on a page without fields — the embedded detail carries them', function (): void {
    app(HeaderActionsRegistry::class)->registerDetailAction('header-actions-test::probe');

    $html = Livewire::test('noerd-test::header-actions-page')
        ->assertOk()
        ->assertSee('HA-PROBE:noerd-test::header-actions-detail/')
        ->assertDontSee('HA-PROBE:noerd-test::header-actions-page/')
        ->html();

    expect(mb_substr_count($html, 'HA-PROBE:'))->toBe(1);
});

it('mounts the action on the field grid of a page that renders fields of its own', function (): void {
    app(HeaderActionsRegistry::class)->registerDetailAction('header-actions-test::probe');

    $html = Livewire::test('noerd-test::header-actions-page', ['withFields' => true])
        ->assertOk()
        ->assertSee('HA-PROBE:noerd-test::header-actions-page/')
        ->assertSee('HA-PROBE:noerd-test::header-actions-detail/')
        ->html();

    expect(mb_substr_count($html, 'HA-PROBE:'))->toBe(2);
});

it('mounts a registered detail action on a shipped detail declaring its model', function (): void {
    app(HeaderActionsRegistry::class)->registerDetailAction('header-actions-test::probe');

    // noerd-user-detail declares $detailModel, so the action receives the model class.
    Livewire::test('noerd::noerd-user-detail')
        ->assertOk()
        ->assertSee('HA-PROBE:noerd::noerd-user-detail/' . NoerdUser::class);
});

it('renders the component\'s own blockActions slot once, in the first block head, next to the registry actions', function (): void {
    app(HeaderActionsRegistry::class)->registerDetailAction('header-actions-test::probe');

    $html = Livewire::test('noerd-test::header-actions-detail', ['withSlot' => true])
        ->assertOk()
        ->assertSeeInOrder(['Zz Header Actions Header', 'Zz Header Actions Block', 'ZZ-SLOT-BUTTON', 'HA-PROBE:noerd-test::header-actions-detail/'])
        ->html();

    // Tab 2 and the nested block render neither the slot nor the registry actions again.
    expect(mb_substr_count($html, 'ZZ-SLOT-BUTTON'))->toBe(1)
        ->and(mb_substr_count($html, 'HA-PROBE:'))->toBe(1);
});

it('renders the blockActions slot without any registered action', function (): void {
    $html = Livewire::test('noerd-test::header-actions-detail', ['withSlot' => true])
        ->assertOk()
        ->assertSeeInOrder(['Zz Header Actions Block', 'ZZ-SLOT-BUTTON'])
        ->assertDontSee('HA-PROBE:')
        ->html();

    expect(mb_substr_count($html, 'ZZ-SLOT-BUTTON'))->toBe(1);
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
