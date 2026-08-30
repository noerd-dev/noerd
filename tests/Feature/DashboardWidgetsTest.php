<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;
use Symfony\Component\Yaml\Yaml;

uses(TestCase::class, RefreshDatabase::class);

function writeDashboardWidgetsFixture(array $widgets): void
{
    $path = base_path('app-configs/dashboard-widgets.yml');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, Yaml::dump(['widgets' => $widgets], 10, 2));
}

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->create());
});

afterEach(function (): void {
    File::delete(base_path('app-configs/dashboard-widgets.yml'));
});

it('renders a permitted widget with its tile-unit size', function (): void {
    Gate::define('canWidgetTest', fn($user): bool => true);

    writeDashboardWidgetsFixture([
        [
            'policy' => 'canWidgetTest',
            'component' => 'noerd::dashboard-widget-test',
            'width' => 2,
            'height' => 2,
        ],
    ]);

    Livewire::test('noerd::layout.dashboard-widgets')
        ->assertOk()
        ->assertCount('widgets', 1)
        ->assertSeeHtml('width: calc(2 * 9rem + 1 * 1.5rem)')
        ->assertSeeHtml('height: calc(2 * 9rem + 1 * 1.5rem)')
        ->assertSee('Test Widget')
        ->assertSee('Test Widget Body');
});

it('hides a widget whose policy is denied', function (): void {
    Gate::define('canWidgetTest', fn($user): bool => false);

    writeDashboardWidgetsFixture([
        [
            'policy' => 'canWidgetTest',
            'component' => 'noerd::dashboard-widget-test',
            'width' => 2,
            'height' => 2,
        ],
    ]);

    Livewire::test('noerd::layout.dashboard-widgets')
        ->assertOk()
        ->assertSet('widgets', [])
        ->assertDontSeeHtml('width: calc(');
});

it('renders nothing when the config file is missing', function (): void {
    Livewire::test('noerd::layout.dashboard-widgets')
        ->assertOk()
        ->assertSet('widgets', []);
});

it('defaults width and height to one tile unit', function (): void {
    Gate::define('canWidgetTest', fn($user): bool => true);

    writeDashboardWidgetsFixture([
        [
            'policy' => 'canWidgetTest',
            'component' => 'noerd::dashboard-widget-test',
        ],
    ]);

    Livewire::test('noerd::layout.dashboard-widgets')
        ->assertOk()
        ->assertSeeHtml('width: calc(1 * 9rem + 0 * 1.5rem)')
        ->assertSeeHtml('height: calc(1 * 9rem + 0 * 1.5rem)');
});

it('renders a widget without a policy key for every user', function (): void {
    writeDashboardWidgetsFixture([
        [
            'component' => 'noerd::dashboard-widget-test',
        ],
    ]);

    Livewire::test('noerd::layout.dashboard-widgets')
        ->assertOk()
        ->assertCount('widgets', 1)
        ->assertSee('Test Widget');
});

it('shows only widgets whose declared app is assigned to the tenant and allowed', function (): void {
    $tenant = \Noerd\Models\Tenant::factory()->create();
    auth()->user()->tenants()->attach($tenant->id);
    \Noerd\Helpers\TenantHelper::setSelectedTenantId($tenant->id);
    foreach (['ZZ_DENIED_APP', 'ZZ_ALLOWED_APP'] as $name) {
        $app = \Noerd\Models\TenantApp::firstOrCreate(
            ['name' => $name],
            ['title' => $name, 'icon' => 'noerd::icons.app', 'route' => 'zz-app-probe', 'is_active' => true],
        );
        $tenant->tenantApps()->syncWithoutDetaching([$app->id]);
    }
    \Noerd\Helpers\TenantHelper::clearCache();

    Gate::define(
        \Noerd\Helpers\AccessHelper::APP_GATE,
        fn(?NoerdUser $user, string $appName): bool => $appName !== 'ZZ_DENIED_APP',
    );

    writeDashboardWidgetsFixture([
        [
            'app' => 'ZZ_DENIED_APP',
            'component' => 'noerd::dashboard-widget-test',
        ],
        [
            'app' => 'ZZ_UNASSIGNED_APP',
            'component' => 'noerd::dashboard-widget-test',
        ],
        [
            'app' => 'ZZ_ALLOWED_APP',
            'component' => 'noerd::dashboard-widget-test',
        ],
    ]);

    Livewire::test('noerd::layout.dashboard-widgets')
        ->assertOk()
        ->assertCount('widgets', 1);
});
