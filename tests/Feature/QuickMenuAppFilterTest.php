<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Noerd\Helpers\AccessHelper;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;
use Noerd\Tests\TestCase;
use Symfony\Component\Yaml\Yaml;

uses(TestCase::class, RefreshDatabase::class);

/*
 | The `app:`/`apps:` keys on quick-menu buttons: a button renders only when at
 | least one of its apps is ASSIGNED to the selected tenant AND the app
 | permission allows it — the quick-menu is tenant-scoped and must not leak a
 | restricted or unassigned app's entry point.
 */

function writeQuickMenuFixture(array $buttons): void
{
    $path = base_path('app-configs/quick-menu.yml');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, Yaml::dump(['buttons' => $buttons], 10, 2));
}

beforeEach(function (): void {
    $user = NoerdUser::factory()->create();
    $this->tenant = Tenant::factory()->create();
    $user->tenants()->attach($this->tenant->id);
    TenantHelper::setSelectedTenantId($this->tenant->id);
    $this->actingAs($user);

    $this->assignApp = function (string $name): void {
        $app = TenantApp::firstOrCreate(
            ['name' => $name],
            ['title' => $name, 'icon' => 'noerd::icons.app', 'route' => 'zz-app-probe', 'is_active' => true],
        );
        $this->tenant->tenantApps()->syncWithoutDetaching([$app->id]);
        TenantHelper::clearCache();
    };
});

afterEach(function (): void {
    File::delete(base_path('app-configs/quick-menu.yml'));
});

it('hides a button whose declared app is denied by the app permission', function (): void {
    ($this->assignApp)('ZZ_DENIED_APP');
    Gate::define(
        AccessHelper::APP_GATE,
        fn(?NoerdUser $user, string $appName): bool => $appName !== 'ZZ_DENIED_APP',
    );

    writeQuickMenuFixture([
        [
            'app' => 'ZZ_DENIED_APP',
            'component' => 'noerd-test::dashboard-widget-test',
        ],
    ]);

    Livewire::test('noerd::layout.quick-menu')
        ->assertOk()
        ->assertDontSee('Test Widget');
});

it('hides a button whose declared app is not assigned to the tenant', function (): void {
    writeQuickMenuFixture([
        [
            'app' => 'ZZ_UNASSIGNED_APP',
            'component' => 'noerd-test::dashboard-widget-test',
        ],
    ]);

    Livewire::test('noerd::layout.quick-menu')
        ->assertOk()
        ->assertDontSee('Test Widget');
});

it('renders a button whose declared app is assigned and allowed', function (): void {
    ($this->assignApp)('ZZ_ALLOWED_APP');

    writeQuickMenuFixture([
        [
            'app' => 'ZZ_ALLOWED_APP',
            'component' => 'noerd-test::dashboard-widget-test',
        ],
    ]);

    Livewire::test('noerd::layout.quick-menu')
        ->assertOk()
        ->assertSee('Test Widget');
});

it('renders a button when ONE of its apps list is usable', function (): void {
    ($this->assignApp)('ZZ_DENIED_APP');
    ($this->assignApp)('ZZ_ALLOWED_APP');
    Gate::define(
        AccessHelper::APP_GATE,
        fn(?NoerdUser $user, string $appName): bool => $appName !== 'ZZ_DENIED_APP',
    );

    writeQuickMenuFixture([
        [
            'apps' => ['ZZ_DENIED_APP', 'ZZ_ALLOWED_APP'],
            'component' => 'noerd-test::dashboard-widget-test',
        ],
    ]);

    Livewire::test('noerd::layout.quick-menu')
        ->assertOk()
        ->assertSee('Test Widget');
});
