<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Livewire\Livewire;
use Noerd\Support\NavigationSubmenus;
use Noerd\Tests\TestCase;

uses(TestCase::class);

/**
 * A request whose route is resolved like a real page request.
 */
function zzSubmenuRequest(string $uri): Request
{
    $request = Request::create($uri);
    $route = app('router')->getRoutes()->match($request);
    $request->setRouteResolver(fn() => $route);

    return $request;
}

/**
 * @return list<array<string, mixed>>
 */
function zzSubmenuBlocks(): array
{
    return [
        [
            'title' => 'Zz Stock',
            'navigations' => [['title' => 'Zz Items', 'route' => 'zz.sub.items']],
        ],
        [
            'title' => 'Zz Settings',
            'submenu' => true,
            'heroicon' => 'cog-6-tooth',
            'navigations' => [
                ['title' => 'Zz General', 'route' => 'zz.sub.general'],
                ['title' => 'Zz Printers', 'link' => '/zz-sub/printers'],
            ],
        ],
    ];
}

beforeEach(function (): void {
    registerTestLivewireRoute('zz-sub/items', 'noerd-test::theme-test', 'zz.sub.items');
    registerTestLivewireRoute('zz-sub/general', 'noerd-test::theme-test', 'zz.sub.general');
    registerTestLivewireRoute('zz-sub/printers', 'noerd-test::theme-test', 'zz.sub.printers');
});

describe('NavigationSubmenus', function (): void {
    it('treats only a block marked submenu with entries as a sub-navigation', function (): void {
        expect(NavigationSubmenus::isSubmenu(['title' => 'A', 'submenu' => true, 'navigations' => [['title' => 'X', 'route' => 'zz.sub.items']]]))->toBeTrue()
            ->and(NavigationSubmenus::isSubmenu(['title' => 'A', 'navigations' => [['title' => 'X', 'route' => 'zz.sub.items']]]))->toBeFalse()
            ->and(NavigationSubmenus::isSubmenu(['title' => 'A', 'submenu' => true, 'navigations' => []]))->toBeFalse()
            ->and(NavigationSubmenus::isSubmenu(['title' => 'A', 'submenu' => 'yes', 'navigations' => [['title' => 'X', 'route' => 'zz.sub.items']]]))->toBeFalse();
    });

    it('opens the sub-navigation whose route entry is the current page', function (): void {
        expect(NavigationSubmenus::activeIndex(zzSubmenuBlocks(), zzSubmenuRequest('/zz-sub/general')))->toBe(1);
    });

    it('opens the sub-navigation whose link entry is the current page', function (): void {
        expect(NavigationSubmenus::activeIndex(zzSubmenuBlocks(), zzSubmenuRequest('/zz-sub/printers')))->toBe(1);
    });

    it('opens no sub-navigation for a page of a normal block', function (): void {
        expect(NavigationSubmenus::activeIndex(zzSubmenuBlocks(), zzSubmenuRequest('/zz-sub/items')))->toBeNull();
    });

    it('keeps string keys of the blocks', function (): void {
        $blocks = ['settings' => zzSubmenuBlocks()[1]];

        expect(NavigationSubmenus::activeIndex($blocks, zzSubmenuRequest('/zz-sub/general')))->toBe('settings');
    });
});

describe('sidebar', function (): void {
    it('renders a sub-navigation as one entry and its own panel with a back link', function (): void {
        $html = html_entity_decode(
            Livewire::test('noerd::layout.sidebar-navigation', ['navigations' => zzSubmenuBlocks()])->html(),
        );

        expect($html)
            ->toContain('enterSubmenu(1)')
            ->toContain('x-ref="submenu-trigger-1"')
            ->toContain('submenu === 1')
            ->toContain('x-ref="submenu-back-1"')
            ->toContain('leaveSubmenu()')
            ->toContain('Zz General')
            ->toContain('Zz Printers')
            // A normal block keeps its collapsible heading.
            ->toContain('wire:click="openStatus(')->toContain('Zz Stock');
    });

    it('starts in the navigation when the page belongs to no sub-navigation', function (): void {
        Livewire::test('noerd::layout.sidebar-navigation', ['navigations' => zzSubmenuBlocks()])
            ->assertSet('openSubmenu', null)
            ->assertSet('expanded', [0 => false]);
    });
});
