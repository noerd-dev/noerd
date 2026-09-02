<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Noerd\Helpers\AccessHelper;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;
use Noerd\Tests\TestCase;
use Symfony\Component\Yaml\Yaml;

uses(TestCase::class, RefreshDatabase::class);

function writeQuickMenuFixture(array $buttons): void
{
    $path = base_path('app-configs/quick-menu.yml');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, Yaml::dump(['buttons' => $buttons], 10, 2));
}

describe('gate', function (): void {
    it('allows every app when no access gate is defined', function (): void {
        expect(Gate::has(AccessHelper::APP_GATE))->toBeFalse()
            ->and(AccessHelper::canAccessApp('ANYTHING'))->toBeTrue()
            ->and(AccessHelper::canAccessApp(null))->toBeTrue()
            ->and(AccessHelper::canAccessApp(''))->toBeTrue();
    });

    it('canUseApp requires tenant assignment AND app permission', function (): void {
        // No tenant selected: nothing is assigned.
        expect(AccessHelper::canUseApp('ANYTHING'))->toBeFalse();

        $user = NoerdUser::factory()->withExampleTenant()->create();
        $this->actingAs($user);
        $tenant = TenantHelper::getSelectedTenant();

        $assigned = TenantApp::create([
            'title' => 'Assigned Probe App',
            'name' => 'ZZ_ASSIGNED_PROBE',
            'icon' => 'noerd::icons.app',
            'route' => 'zz-assigned-probe',
            'is_active' => true,
        ]);
        $tenant->tenantApps()->attach($assigned->id, ['is_hidden' => false]);
        TenantHelper::clearCache();

        // Assigned + no denying gate: usable (case-insensitive).
        expect(AccessHelper::canUseApp('zz_assigned_probe'))->toBeTrue()
            // Not assigned to the tenant: unusable even though the gate would allow.
            ->and(AccessHelper::canUseApp('ZZ_UNASSIGNED_PROBE'))->toBeFalse()
            // One usable app out of several suffices.
            ->and(AccessHelper::canUseApp('ZZ_UNASSIGNED_PROBE', 'ZZ_ASSIGNED_PROBE'))->toBeTrue();

        // Assigned but denied by the app permission: unusable.
        Gate::define(AccessHelper::APP_GATE, fn(?NoerdUser $u, string $appName): bool => false);
        expect(AccessHelper::canUseApp('ZZ_ASSIGNED_PROBE'))->toBeFalse();
    });
});

describe('app bar', function (): void {
    it('hides an app from the app bar when the access gate denies it', function (): void {
        if (! Route::has('app-permission-test')) {
            Route::get('/app-permission-test', fn(): string => 'ok')->name('app-permission-test');
        }

        $user = NoerdUser::factory()->adminUser()->create();
        $tenant = $user->adminTenants()->first();

        $allowedApp = TenantApp::create([
            'title' => 'Allowed Probe App',
            'name' => 'ALLOWED_PROBE',
            'icon' => 'noerd::icons.app',
            'route' => 'app-permission-test',
            'is_active' => true,
        ]);
        $deniedApp = TenantApp::create([
            'title' => 'Denied Probe App',
            'name' => 'DENIED_PROBE',
            'icon' => 'noerd::icons.app',
            'route' => 'app-permission-test',
            'is_active' => true,
        ]);
        $tenant?->tenantApps()->attach($allowedApp->id, ['is_hidden' => false]);
        $tenant?->tenantApps()->attach($deniedApp->id, ['is_hidden' => false]);
        $this->actingAs($user);

        // Synthetic gate, standing in for a project-defined restriction: the app
        // bar must consult the gate, not the tenant assignment alone.
        Gate::define(AccessHelper::APP_GATE, fn(?NoerdUser $user, string $appName): bool => $appName !== 'DENIED_PROBE');

        Livewire::test('noerd::layout.app-bar')
            ->assertSee('Allowed Probe App')
            ->assertDontSee('Denied Probe App');
    });
});

/*
 | The `app:`/`apps:` keys on quick-menu buttons: a button renders only when at
 | least one of its apps is ASSIGNED to the selected tenant AND the app
 | permission allows it — the quick-menu is tenant-scoped and must not leak a
 | restricted or unassigned app's entry point. The AND logic itself is owned by
 | `canUseApp requires tenant assignment AND app permission` above; these two
 | cases only prove the quick menu consults it, through both key spellings.
 */
describe('quick menu', function (): void {
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
});
