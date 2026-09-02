<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

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
    registerTestLivewireRoute('zz-nav-accounts', 'noerd-test::theme-test', 'zz.nav.accounts');
    registerTestLivewireRoute('zz-nav-account/{modelId}', 'noerd-test::theme-test', 'zz.nav.account.detail');
});

describe('modal routes', function (): void {
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
});

/**
 * The sidebar must skip navigation entries whose primary `route:` is not
 * registered (e.g. a stale entry left behind by an uninstalled module) instead
 * of letting an unguarded route() call take the whole page down. Uses a
 * synthetic navigation fixture — never asserts shipped configuration.
 */
describe('unregistered routes', function (): void {
    beforeEach(function (): void {
        $this->navigationPath = base_path('app-configs/setup/navigation.yml');
        $this->navigationBackup = File::exists($this->navigationPath) ? File::get($this->navigationPath) : null;
    });

    afterEach(function (): void {
        if ($this->navigationBackup !== null) {
            File::put($this->navigationPath, $this->navigationBackup);
        } else {
            File::delete($this->navigationPath);
        }
    });

    it('renders the page and hides entries whose route is not registered', function (): void {
        File::ensureDirectoryExists(dirname($this->navigationPath));
        File::put($this->navigationPath, <<<'YAML'
-
  title: Setup
  name: setup
  hidden: true
  route: noerd.setup
  block_menus:
    -
      title: Administration
      navigations:
        -
          title: 'Zz Valid Entry'
          route: noerd.users
          heroicon: users
        -
          title: 'Zz Stale Entry'
          route: zz-not-registered-route
          heroicon: users
        -
          title: 'Zz Link Entry'
          link: /zz-somewhere
        -
          title: 'Zz Modal Entry'
          component: zz-some-modal
YAML);

        $admin = NoerdUser::factory()->adminUser()->create();

        $this->actingAs($admin)->get('/setup')
            ->assertOk()
            ->assertSee('Zz Valid Entry')
            ->assertDontSee('Zz Stale Entry')
            ->assertSee('Zz Link Entry')
            ->assertSee('Zz Modal Entry');
    });
});
