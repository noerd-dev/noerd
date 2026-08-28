<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());

    Livewire::addNamespace('page-actions-test', viewPath: __DIR__ . '/fixtures/page-actions');
});

function syntheticProbeAction(array $overrides = []): array
{
    return array_merge([
        'label' => 'Synthetic Probe Action',
        'action' => 'doProbe',
        'requiresId' => false,
    ], $overrides);
}

it('auto-renders YAML actions without a detail-actions include in the blade', function (): void {
    Livewire::test('page-actions-test::auto-probe', [
        'pageLayout' => ['actions' => [syntheticProbeAction()]],
    ])
        ->assertOk()
        ->assertSee('Synthetic Probe Action')
        ->assertSeeHtml('wire:click="doProbe"');
});

it('auto-renders YAML actions on a real detail component', function (): void {
    $component = Livewire::test('noerd::noerd-user-detail')->assertOk();

    $component
        ->set('pageLayout', array_merge($component->get('pageLayout'), [
            'actions' => [syntheticProbeAction()],
        ]))
        ->assertSee('Synthetic Probe Action')
        ->assertSeeHtml('wire:click="doProbe"');
});

it('applies the requiresId gating through the auto-render path', function (): void {
    Livewire::test('page-actions-test::auto-probe', [
        'pageLayout' => ['actions' => [syntheticProbeAction(['requiresId' => true])]],
    ])
        ->assertOk()
        ->assertDontSee('Synthetic Probe Action');
});

it('does not auto-render actions in quick-create mode', function (): void {
    Livewire::test('page-actions-test::auto-probe', [
        'quickCreate' => true,
        'pageLayout' => ['actions' => [syntheticProbeAction()]],
    ])
        ->assertOk()
        ->assertDontSee('Synthetic Probe Action');
});

it('does not auto-render actions in an embedded detail', function (): void {
    Livewire::test('page-actions-test::auto-probe', [
        'embedded' => true,
        'pageLayout' => ['actions' => [syntheticProbeAction()]],
    ])
        ->assertOk()
        ->assertDontSee('Synthetic Probe Action');
});

it('renders list components without a pageLayout property normally', function (): void {
    Livewire::test('noerd::noerd-users-list')->assertOk();
});

it('renders the actions exactly once with the opt-out plus an explicit include', function (): void {
    $component = Livewire::test('page-actions-test::opt-out-probe', [
        'pageLayout' => ['actions' => [syntheticProbeAction()]],
    ])->assertOk();

    // The label also appears in the serialized wire:snapshot (pageLayout is a
    // public property), so count the rendered button attribute instead.
    expect(mb_substr_count($component->html(), 'wire:click="doProbe"'))->toBe(1);
});

it('resolves url actions through the detailActionUrls convention', function (): void {
    Livewire::test('page-actions-test::url-probe', [
        'pageLayout' => ['actions' => [syntheticProbeAction(['url' => 'probeUrl'])]],
    ])
        ->assertOk()
        ->assertSee('Synthetic Probe Action')
        ->assertSeeHtml('href="https://example.test/probe"');
});

it('hides url actions whose key is missing from detailActionUrls', function (): void {
    Livewire::test('page-actions-test::url-probe', [
        'pageLayout' => ['actions' => [syntheticProbeAction(['url' => 'missingUrl'])]],
    ])
        ->assertOk()
        ->assertDontSee('Synthetic Probe Action');
});
