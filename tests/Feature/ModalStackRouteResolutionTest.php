<?php

declare(strict_types=1);

use Livewire\Livewire;
use Noerd\Tests\TestCase;

uses(TestCase::class);

/**
 * The two ways a noerd modal can be opened, proven on the modal stack itself:
 * by ROUTE name (resolved to the component behind it, browser URL rewritten) and
 * by COMPONENT name (opened as-is, URL untouched) — plus the precedence between
 * them when a caller supplies both.
 *
 * The modal stack lives in the sibling noerd-modal package, whose own suite cannot
 * run inside this project's test run (it corrupts the shared test database, see the
 * exclude in phpunit.xml). This mirrors that contract here against synthetic zz.*
 * routes, so a regression in either flavour is caught by the normal suite.
 *
 * Which of the two a given YAML/component picks is per-installation configuration
 * and is deliberately NOT asserted anywhere — only that both keep working.
 */
function openModal(array $params): array
{
    $component = Livewire::test('noerd-modal::noerd-modal')->dispatch('noerdModal', ...$params);

    return [$component, array_values($component->get('modals'))];
}

it('resolves a route name to its component and rewrites the url', function (): void {
    registerTestLivewireRoute('zz-modal-record/{modelId}', 'noerd-test::theme-test', 'zz.modal.record');

    [$component, $modals] = openModal(['route' => 'zz.modal.record', 'arguments' => ['modelId' => 5]]);

    expect($modals[0]['componentName'])->toBe('noerd-test::theme-test')
        ->and($modals[0]['url'])->toBe(route('zz.modal.record', ['modelId' => 5, 'modal' => 'true']));

    $component->assertDispatched('set-modal-url');
});

it('opens a component name as-is and leaves the url alone', function (): void {
    [$component, $modals] = openModal([
        'modalComponent' => 'noerd-test::theme-test',
        'arguments' => ['modelId' => 5],
    ]);

    expect($modals[0]['componentName'])->toBe('noerd-test::theme-test')
        ->and($modals[0]['url'])->toBeNull();

    $component->assertNotDispatched('set-modal-url');
});

it('prefers the route over the fallback component when both are given', function (): void {
    registerTestLivewireRoute('zz-modal-wins/{modelId}', 'noerd-test::positions-theme-test', 'zz.modal.wins');

    [, $modals] = openModal([
        'route' => 'zz.modal.wins',
        'modalComponent' => 'noerd-test::theme-test',
        'arguments' => ['modelId' => 5],
    ]);

    expect($modals[0]['componentName'])->toBe('noerd-test::positions-theme-test');
});

it('falls back to the component when the route is not registered', function (): void {
    [$component, $modals] = openModal([
        'route' => 'zz.modal.route.that.does.not.exist',
        'modalComponent' => 'noerd-test::theme-test',
        'arguments' => ['modelId' => 5],
    ]);

    expect($modals[0]['componentName'])->toBe('noerd-test::theme-test')
        ->and($modals[0]['url'])->toBeNull();

    $component->assertNotDispatched('set-modal-url');
});

it('opens nothing when neither a registered route nor a component is given', function (): void {
    [, $modals] = openModal(['route' => 'zz.modal.route.that.does.not.exist', 'arguments' => []]);

    expect($modals)->toBeEmpty();
});

it('carries the new sentinel in the url for a create modal', function (): void {
    registerTestLivewireRoute('zz-modal-new/{modelId}', 'noerd-test::theme-test', 'zz.modal.new');

    [, $modals] = openModal(['route' => 'zz.modal.new', 'arguments' => ['modelId' => null]]);

    expect($modals[0]['url'])->toBe(route('zz.modal.new', ['modelId' => 'new', 'modal' => 'true']));
});

it('resolves the route but keeps the url when rewriteUrl is false', function (): void {
    registerTestLivewireRoute('zz-modal-no-rewrite/{modelId}', 'noerd-test::theme-test', 'zz.modal.no.rewrite');

    [$component, $modals] = openModal([
        'route' => 'zz.modal.no.rewrite',
        'arguments' => ['modelId' => 5],
        'rewriteUrl' => false,
    ]);

    expect($modals[0]['componentName'])->toBe('noerd-test::theme-test')
        ->and($modals[0]['url'])->toBeNull();

    $component->assertNotDispatched('set-modal-url');
});

it('suppresses the url rewrite for an argument the route cannot express', function (): void {
    registerTestLivewireRoute('zz-modal-filtered', 'noerd-test::theme-test', 'zz.modal.filtered');

    [, $modals] = openModal(['route' => 'zz.modal.filtered', 'arguments' => ['accountId' => 5]]);

    expect($modals[0]['componentName'])->toBe('noerd-test::theme-test')
        ->and($modals[0]['url'])->toBeNull();
});

/**
 * Only `quickCreate` is used as the chrome argument here: the fixture component
 * cannot mount a `relations` array. That `relations` travels as a URL-neutral
 * argument is covered at the dispatch level by ListDetailRouteFallbackTest.
 */
it('still rewrites the url for chrome-only arguments', function (): void {
    registerTestLivewireRoute('zz-modal-chrome/{modelId}', 'noerd-test::theme-test', 'zz.modal.chrome');

    [, $modals] = openModal([
        'route' => 'zz.modal.chrome',
        'arguments' => ['modelId' => 5, 'quickCreate' => true],
    ]);

    expect($modals[0]['url'])->toBe(route('zz.modal.chrome', ['modelId' => 5, 'modal' => 'true']));
});

it('restores the url only once the routed modal itself is closed', function (): void {
    registerTestLivewireRoute('zz-modal-stack/{modelId}', 'noerd-test::theme-test', 'zz.modal.stack');

    $component = Livewire::test('noerd-modal::noerd-modal')
        ->dispatch('noerdModal', route: 'zz.modal.stack', arguments: ['modelId' => 5])
        ->dispatch('noerdModal', modalComponent: 'noerd-test::theme-test', arguments: ['sub' => true]);

    $component->dispatch('closeTopModal')->assertNotDispatched('restore-modal-url');
    $component->dispatch('closeTopModal')->assertDispatched('restore-modal-url');
});
