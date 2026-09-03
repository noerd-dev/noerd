<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Support\LayoutState;
use Noerd\Tests\TestCase;
use Symfony\Component\Yaml\Yaml;

uses(TestCase::class, RefreshDatabase::class);

/*
 | The chrome components render component names straight into
 | <livewire:dynamic-component> and interpolate layout state into inline
 | styles. Everything the browser must not be able to steer is locked, every
 | client-callable action re-validates its input.
 */

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->create());
});

it('locks the widget list against client updates', function (): void {
    Livewire::test('noerd::layout.dashboard-widgets')
        ->set('widgets', [['component' => 'noerd-test::dashboard-widget-test']]);
})->throws(CannotUpdateLockedPropertyException::class);

it('locks the quick-menu button config against client updates', function (): void {
    Livewire::test('noerd::layout.quick-menu')
        ->set('config', ['buttons' => [['component' => 'noerd-test::dashboard-widget-test']]]);
})->throws(CannotUpdateLockedPropertyException::class);

it('locks the top-bar component list against client updates', function (): void {
    Livewire::test('noerd::layout.top-bar')
        ->set('topBarComponents', ['noerd-test::dashboard-widget-test']);
})->throws(CannotUpdateLockedPropertyException::class);

it('locks the banner list against client updates', function (): void {
    Livewire::test('noerd::layout.banner')
        ->set('banners', [['priority' => 1, 'component' => 'noerd-test::dashboard-widget-test']]);
})->throws(CannotUpdateLockedPropertyException::class);

it('locks the environment banner presentation against client updates', function (): void {
    Livewire::test('noerd::layout.environment-banner')->set('label', 'Production');
})->throws(CannotUpdateLockedPropertyException::class);

it('locks the impersonation banner state against client updates', function (): void {
    Livewire::test('noerd::layout.impersonation-banner')->set('isImpersonating', true);
})->throws(CannotUpdateLockedPropertyException::class);

it('locks the navigation data of the sidebar navigation', function (): void {
    Livewire::test('noerd::layout.sidebar-navigation')
        ->set('navigation', [['title' => 'Evil', 'route' => 'noerd.apps']]);
})->throws(CannotUpdateLockedPropertyException::class);

it('keeps the block-menu open flags client writable next to the locked navigation', function (): void {
    $navigations = [
        [
            'title' => 'Block',
            'show' => false,
            'navigations' => [['title' => 'Item', 'route' => 'noerd.apps']],
        ],
    ];

    Livewire::test('noerd::layout.sidebar-navigation', ['navigations' => $navigations])
        ->assertSet('expanded.0', false)
        ->set('expanded.0', true)
        ->assertSet('expanded.0', true)
        ->assertSet('navigations', $navigations);
});

it('rejects a sidebar width that is not a plain pixel value', function (): void {
    Livewire::test('noerd::layout.sidebar')
        ->call('saveSidebarWidth', '300px; } body { display: none }')
        ->assertStatus(422);

    Livewire::test('noerd::layout.sidebar')
        ->call('saveSidebarWidth', '300')
        ->assertStatus(422);
});

it('stores a plain pixel sidebar width', function (): void {
    Livewire::test('noerd::layout.sidebar')
        ->call('saveSidebarWidth', '320px')
        ->assertOk();

    expect(LayoutState::navigationWidth())->toBe('320px');
});

it('refuses to stop an impersonation that never started', function (): void {
    Livewire::test('noerd::layout.impersonation-banner')
        ->call('stopImpersonating')
        ->assertForbidden();
});

it('refuses to switch to a tenant the user cannot access', function (): void {
    $foreignTenant = Tenant::factory()->create();

    Livewire::test('noerd::layout.tenant-switcher')
        ->call('switchTenant', $foreignTenant->id)
        ->assertForbidden();
});

it('switches to a tenant the user belongs to', function (): void {
    $tenant = Tenant::factory()->create();
    auth()->user()->tenants()->attach($tenant->id);
    auth()->user()->unsetRelation('tenants');

    Livewire::test('noerd::layout.tenant-switcher')
        ->call('switchTenant', $tenant->id)
        ->assertRedirect('/');
});

it('renders quick-menu buttons from the config without exposing them to the client', function (): void {
    $path = base_path('app-configs/quick-menu.yml');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, Yaml::dump(['buttons' => [['component' => 'noerd-test::dashboard-widget-test']]], 10, 2));

    Livewire::test('noerd::layout.quick-menu')
        ->assertOk()
        ->assertSee('Test Widget');

    File::delete($path);
});
