<?php

declare(strict_types=1);

use Livewire\Livewire;
use Noerd\Tests\TestCase;

uses(TestCase::class);

/**
 * @param  array<string, mixed>  $navi
 */
function renderNavigationElement(array $navi): string
{
    return html_entity_decode(
        Livewire::test('noerd::layout.sidebar-navigation-element', ['navi' => $navi])->html(),
    );
}

beforeEach(function (): void {
    registerTestLivewireRoute('zz-nav-accounts', 'noerd::theme-test', 'zz.nav.accounts');
    registerTestLivewireRoute('zz-nav-account/{modelId}', 'noerd::theme-test', 'zz.nav.account.detail');
});

it('renders the + button as $modalRoute for newRoute', function (): void {
    $html = renderNavigationElement([
        'title' => 'Accounts',
        'route' => 'zz.nav.accounts',
        'newRoute' => 'zz.nav.account.detail',
    ]);

    expect($html)->toContain('$modalRoute(')
        ->toContain('zz.nav.account.detail');
});

it('keeps the + button on $modal for newComponent', function (): void {
    $html = renderNavigationElement([
        'title' => 'Accounts',
        'route' => 'zz.nav.accounts',
        'newComponent' => 'zz::account-detail',
    ]);

    expect($html)->toContain('$modal(')
        ->toContain('zz::account-detail')
        ->not->toContain('$modalRoute(');
});

it('prefers newRoute over newComponent and keeps the component as fallback', function (): void {
    $html = renderNavigationElement([
        'title' => 'Accounts',
        'route' => 'zz.nav.accounts',
        'newRoute' => 'zz.nav.account.detail',
        'newComponent' => 'zz::account-detail',
    ]);

    expect($html)->toContain('$modalRoute(')
        ->toContain('fallbackComponent')
        ->toContain('zz::account-detail');
});

it('falls back to newComponent when newRoute is not registered', function (): void {
    $html = renderNavigationElement([
        'title' => 'Accounts',
        'route' => 'zz.nav.accounts',
        'newRoute' => 'zz.nav.route.that.does.not.exist',
        'newComponent' => 'zz::account-detail',
    ]);

    expect($html)->toContain('$modal(')
        ->toContain('zz::account-detail')
        ->not->toContain('$modalRoute(');
});

it('opens a modalRoute entry as a modal instead of a navigate link', function (): void {
    $html = renderNavigationElement([
        'title' => 'Times',
        'modalRoute' => 'zz.nav.accounts',
    ]);

    expect($html)->toContain('$modalRoute(')
        ->toContain('zz.nav.accounts')
        ->not->toContain('wire:navigate');
});

it('still renders a plain route entry as a wire:navigate link', function (): void {
    $html = renderNavigationElement([
        'title' => 'Accounts',
        'route' => 'zz.nav.accounts',
    ]);

    expect($html)->toContain('wire:navigate')
        ->not->toContain('$modalRoute(')
        ->not->toContain('$modal(');
});

it('opens the + button in the narrow panel for a quickCreate newRoute', function (): void {
    $html = renderNavigationElement([
        'title' => 'Accounts',
        'route' => 'zz.nav.accounts',
        'newRoute' => 'zz.nav.account.detail',
        'quickCreate' => true,
    ]);

    expect($html)->toContain('$modalRoute(')
        ->toContain('narrow')
        ->toContain('quickCreate');
});

it('passes the entry arguments to the modal it opens', function (): void {
    $html = renderNavigationElement([
        'title' => 'Accounts',
        'component' => 'zz::accounts-list',
        'arguments' => ['accountType' => 'vip'],
    ]);

    expect($html)->toContain('$modal(')
        ->toContain('"accountType":"vip"');
});
