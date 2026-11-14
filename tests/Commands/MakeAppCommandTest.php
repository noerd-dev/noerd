<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Noerd\Models\TenantApp;
use Noerd\Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

/**
 * The command scaffolds the app's dashboard into the application root (Blade
 * component, route, navigation) or, in module mode, a package into app-modules/
 * (plus a require in composer.json). Snapshot what is there before each test and
 * remove everything the test generated afterwards.
 */
beforeEach(function (): void {
    $routeFile = base_path('routes/web.php');
    $composerFile = base_path('composer.json');

    $this->routeFileExisted = File::exists($routeFile);
    $this->originalRoutes = $this->routeFileExisted ? File::get($routeFile) : null;
    $this->originalComposer = File::exists($composerFile) ? File::get($composerFile) : null;
    $this->existingDashboards = File::glob(base_path('resources/views/components/*-dashboard.blade.php'));
    $this->existingAppConfigs = File::directories(base_path('app-configs'));
    $this->existingModules = File::directories(base_path('app-modules'));
    $this->existingMigrations = File::glob(database_path('migrations/*.php'));
});

afterEach(function (): void {
    $routeFile = base_path('routes/web.php');

    if ($this->routeFileExisted) {
        File::put($routeFile, $this->originalRoutes);
    } else {
        File::delete($routeFile);
    }

    if ($this->originalComposer !== null) {
        File::put(base_path('composer.json'), $this->originalComposer);
    }

    foreach (File::directories(base_path('app-modules')) as $module) {
        if (! in_array($module, $this->existingModules, true)) {
            File::deleteDirectory($module);
        }
    }

    foreach (File::glob(base_path('resources/views/components/*-dashboard.blade.php')) as $dashboard) {
        if (! in_array($dashboard, $this->existingDashboards, true)) {
            File::delete($dashboard);
        }
    }

    foreach (File::directories(base_path('app-configs')) as $appConfig) {
        if (! in_array($appConfig, $this->existingAppConfigs, true)) {
            File::deleteDirectory($appConfig);
        }
    }

    foreach (File::glob(database_path('migrations/*.php')) as $migration) {
        if (! in_array($migration, $this->existingMigrations, true)) {
            File::delete($migration);
        }
    }
});

it('successfully creates a app with all parameters', function (): void {
    $this->artisan('noerd:make-app', [
        '--title' => 'Test Application',
        '--name' => 'TEST_APP',
        '--icon' => 'icons.test',
        '--active' => '1',
    ])->assertExitCode(0);

    // One naming scheme with the module mode: TEST_APP → TEST-APP, dashboard route `{app}`.
    expect(TenantApp::where('name', 'TEST-APP')->exists())->toBeTrue();

    $app = TenantApp::where('name', 'TEST-APP')->first();
    expect($app->title)->toBe('Test Application');
    expect($app->icon)->toBe('icons.test');
    expect($app->route)->toBe('test-app');
    expect($app->is_active)->toBeTrue();
});

it('registers the project app through the idempotent migration a deployment runs', function (): void {
    $this->artisan('noerd:make-app', [
        '--title' => 'Zz Registered',
        '--name' => 'ZZ_REGISTERED',
        '--icon' => 'icons.zz',
    ])->assertExitCode(0);

    $migrations = File::glob(database_path('migrations/*_add_zz-registered_tenant_app.php'));
    expect($migrations)->toHaveCount(1);

    // A fresh database (the next deployment) registers the app from the file alone.
    TenantApp::where('name', 'ZZ-REGISTERED')->delete();
    (require $migrations[0])->up();

    expect(TenantApp::where('name', 'ZZ-REGISTERED')->value('route'))->toBe('zz-registered');

    // Idempotent: running it again keeps one row.
    (require $migrations[0])->up();
    expect(TenantApp::where('name', 'ZZ-REGISTERED')->count())->toBe(1);
});

it('scaffolds a dashboard for the new app and uses its route without asking', function (): void {
    $this->artisan('noerd:make-app', [
        '--title' => 'Time Booking',
        '--name' => 'TB',
        '--icon' => 'icons.tb',
    ])->assertExitCode(0);

    expect(TenantApp::where('name', 'TB')->value('route'))->toBe('tb');

    $dashboard = base_path('resources/views/components/tb-dashboard.blade.php');
    $stub = File::get(dirname(__DIR__, 2) . '/src/Commands/stubs/resource/dashboard.blade.stub');
    expect(File::exists($dashboard))->toBeTrue()
        ->and(File::get($dashboard))->toBe(str_replace('{{appName}}', 'tb', $stub));

    $routes = File::get(base_path('routes/web.php'));
    expect($routes)
        ->toContain("Route::livewire('tb', 'tb-dashboard')->name('tb');")
        ->toContain("Route::middleware(['noerd', 'app-access:tb'])");

    $navigation = base_path('app-configs/tb/navigation.yml');
    expect(File::exists($navigation))->toBeTrue();

    $parsed = Symfony\Component\Yaml\Yaml::parse(File::get($navigation));
    expect($parsed[0]['title'])->toBe('Time Booking')
        ->and($parsed[0]['name'])->toBe('tb')
        ->and($parsed[0]['route'])->toBe('tb')
        ->and($parsed[0]['blockMenus'][0]['navigations'][0]['route'])->toBe('tb');
});

it('creates the routes file when the project has none yet', function (): void {
    File::delete(base_path('routes/web.php'));

    $this->artisan('noerd:make-app', [
        '--title' => 'Fresh App',
        '--name' => 'FRESH',
        '--icon' => 'icons.fresh',
    ])->assertExitCode(0);

    $routes = File::get(base_path('routes/web.php'));
    expect($routes)
        ->toStartWith("<?php\n")
        ->toContain("->name('fresh');");
});

it('keeps an existing navigation and only inserts the dashboard entry', function (): void {
    File::ensureDirectoryExists(base_path('app-configs/existing-nav'));
    File::put(base_path('app-configs/existing-nav/navigation.yml'), implode("\n", [
        '- title: Existing',
        '  name: existing-nav',
        '  route: existing-nav',
        '  blockMenus:',
        '    - title: Overview',
        '      navigations:',
        '        - title: Things',
        '          route: existing-nav.things',
        '          heroicon: cube',
        '',
    ]));

    $this->artisan('noerd:make-app', [
        '--title' => 'Existing',
        '--name' => 'EXISTING_NAV',
        '--icon' => 'icons.existing',
    ])->assertExitCode(0);

    // The app's own `route:` is no entry: the dashboard leads the first block, the rest stays.
    $parsed = Symfony\Component\Yaml\Yaml::parse(File::get(base_path('app-configs/existing-nav/navigation.yml')));
    expect(array_column($parsed[0]['blockMenus'][0]['navigations'], 'route'))->toBe(['existing-nav', 'existing-nav.things']);
});

it('never adds a second dashboard route or entry under the earlier route name', function (): void {
    File::ensureDirectoryExists(base_path('routes'));
    File::append(base_path('routes/web.php'), "\nRoute::livewire('zzold', 'zzold-dashboard')->name('zzold.dashboard');\n");
    File::ensureDirectoryExists(base_path('app-configs/zzold'));
    File::put(base_path('app-configs/zzold/navigation.yml'), "- title: Zz Old\n  name: zzold\n  route: zzold.dashboard\n  blockMenus:\n    - title: Overview\n      navigations:\n        - title: Dashboard\n          route: zzold.dashboard\n          heroicon: home\n");
    $navigationBefore = File::get(base_path('app-configs/zzold/navigation.yml'));

    $this->artisan('noerd:make-dashboard', ['--app' => 'zzold', '--no-interaction' => true])
        ->expectsOutputToContain("Dashboard route 'zzold.dashboard' already exists")
        ->assertExitCode(0);

    expect(mb_substr_count(File::get(base_path('routes/web.php')), "zzold-dashboard')"))->toBe(1)
        ->and(File::get(base_path('app-configs/zzold/navigation.yml')))->toBe($navigationBefore);
});

it('points the app at an explicit route and skips the dashboard scaffold', function (): void {
    $this->artisan('noerd:make-app', [
        '--title' => 'Custom Route App',
        '--name' => 'CUSTOM_ROUTE',
        '--icon' => 'icons.custom',
        '--route' => 'custom.index',
    ])->assertExitCode(0);

    expect(TenantApp::where('name', 'CUSTOM-ROUTE')->value('route'))->toBe('custom.index')
        ->and(File::exists(base_path('resources/views/components/custom-route-dashboard.blade.php')))->toBeFalse()
        ->and(File::exists(base_path('app-configs/custom-route/navigation.yml')))->toBeFalse();
});

it('creates an inactive tenant app when active is set to 0', function (): void {
    $this->artisan('noerd:make-app', [
        '--title' => 'Inactive App',
        '--name' => 'INACTIVE_APP',
        '--icon' => 'icons.inactive',
        '--active' => '0',
    ])->assertExitCode(0);

    $app = TenantApp::where('name', 'INACTIVE-APP')->first();
    expect($app->is_active)->toBeFalse();
});

it('defaults to active when active parameter is not provided', function (): void {
    $this->artisan('noerd:make-app', [
        '--title' => 'Default Active App',
        '--name' => 'DEFAULT_ACTIVE',
        '--icon' => 'icons.default',
    ])->assertExitCode(0);

    $app = TenantApp::where('name', 'DEFAULT-ACTIVE')->first();
    expect($app->is_active)->toBeTrue();
});

it('rejects invalid app input', function (array $options, string $error): void {
    $appCountBefore = TenantApp::count();

    // The error message is the behaviour here: it tells the operator what to fix.
    $this->artisan('noerd:make-app', $options)
        ->expectsOutput($error)
        ->assertExitCode(1);

    expect(TenantApp::count())->toBe($appCountBefore);
})->with([
    'no field at all' => [
        ['--title' => '', '--name' => '', '--icon' => ''],
        'All fields (title, name, icon) are required.',
    ],
    'only some fields' => [
        ['--title' => 'Test Title', '--name' => 'MISSING_FIELDS', '--icon' => ''],
        'All fields (title, name, icon) are required.',
    ],
    'special characters in the name' => [
        ['--title' => 'Special Chars App', '--name' => 'SPECIAL-CHARS!', '--icon' => 'icons.test'],
        'App name must start with a letter and contain only letters, digits and hyphens (e.g., CMS, MEDIA, MY-APP, SHOP2).',
    ],
    'a leading digit' => [
        ['--title' => 'Digit App', '--name' => '2SHOP', '--icon' => 'icons.test'],
        'App name must start with a letter and contain only letters, digits and hyphens (e.g., CMS, MEDIA, MY-APP, SHOP2).',
    ],
]);

it('normalizes the app name to the uppercase hyphen form of the module mode', function (string $input, string $expected): void {
    $this->artisan('noerd:make-app', [
        '--title' => 'Normalized App',
        '--name' => $input,
        '--icon' => 'icons.test',
    ])->assertExitCode(0);

    expect(TenantApp::where('name', $expected)->exists())->toBeTrue();
})->with([
    'lowercase with space' => ['lowercase app', 'LOWERCASE-APP'],
    'hyphens kept' => ['hyphen-name', 'HYPHEN-NAME'],
    'uppercase with space' => ['SPACED NAME', 'SPACED-NAME'],
    'underscores' => ['UNDERSCORE_NAME_APP', 'UNDERSCORE-NAME-APP'],
    'digits' => ['shop2', 'SHOP2'],
    'single word' => ['SINGLE', 'SINGLE'],
]);

it('fails when app name already exists', function (): void {
    // First, create an app
    TenantApp::create([
        'title' => 'Existing App',
        'name' => 'EXISTING-APP',
        'icon' => 'icons.existing',
        'route' => 'existing.route',
        'is_active' => true,
    ]);

    // Try to create another app with the same name
    $this->artisan('noerd:make-app', [
        '--title' => 'Duplicate App',
        '--name' => 'EXISTING_APP',
        '--icon' => 'icons.duplicate',
    ])
        ->expectsOutput("App with name 'EXISTING-APP' already exists.")
        ->assertExitCode(1);

    expect(File::exists(base_path('resources/views/components/existing-app-dashboard.blade.php')))->toBeFalse();
});

it('scaffolds the app as a module and leaves the registration to its install command', function (): void {
    $this->artisan('noerd:make-app', [
        '--title' => 'Zz Probe Suite',
        '--name' => 'ZZ_PROBE',
        '--icon' => 'cube',
        '--module' => true,
    ])->assertExitCode(0);

    $module = base_path('app-modules/zz-probe');

    // The module boilerplate — dashboard, navigation, install command, migration stub —
    // and no model: resources are generated later with noerd:make-resource.
    expect(File::isDirectory($module))->toBeTrue()
        ->and(File::exists("{$module}/resources/views/components/zz-probe-dashboard.blade.php"))->toBeTrue()
        ->and(File::exists("{$module}/app-configs/zz-probe/navigation.yml"))->toBeTrue()
        ->and(File::exists("{$module}/app-configs/stubs/add_zz-probe_tenant_app.php.stub"))->toBeTrue()
        ->and(File::glob("{$module}/resources/views/components/*-list.blade.php"))->toBe([])
        ->and(File::glob("{$module}/src/Models/*.php"))->toBe([])
        ->and(File::isDirectory("{$module}/resources/views/components/icons"))->toBeFalse();

    $installCommand = File::get("{$module}/src/Commands/ZzProbeInstallCommand.php");
    expect($installCommand)
        ->toContain("return 'heroicon:outline:cube';")
        ->toContain("return 'Zz Probe Suite';");

    $routes = File::get("{$module}/routes/zz-probe-routes.php");
    expect($routes)->toContain("Route::livewire('zz-probe', 'zz-probe::zz-probe-dashboard')->name('zz-probe');");

    $composer = json_decode(File::get(base_path('composer.json')), true);
    expect($composer['require'])->toHaveKey('noerd/zz-probe');

    // Nothing lands in the project root and no tenant_apps row is written —
    // noerd:install-zz-probe registers the app (and stays re-runnable).
    expect(TenantApp::where('name', 'ZZ-PROBE')->exists())->toBeFalse()
        ->and(TenantApp::where('name', 'ZZ_PROBE')->exists())->toBeFalse()
        ->and(File::exists(base_path('resources/views/components/zz_probe-dashboard.blade.php')))->toBeFalse()
        ->and(File::exists(base_path('app-configs/zz_probe/navigation.yml')))->toBeFalse();
});

it('rejects module mode combined with an explicit route', function (): void {
    $this->artisan('noerd:make-app', [
        '--title' => 'Zz Routed',
        '--name' => 'ZZ_ROUTED',
        '--icon' => 'cube',
        '--module' => true,
        '--route' => 'custom.index',
    ])
        ->expectsOutput('The --route option cannot be combined with --module: a module ships its own dashboard route.')
        ->assertExitCode(1);

    expect(File::isDirectory(base_path('app-modules/zz-routed')))->toBeFalse();
});

it('refuses to scaffold a module over an existing module directory', function (): void {
    File::ensureDirectoryExists(base_path('app-modules/zz-taken'));

    $this->artisan('noerd:make-app', [
        '--title' => 'Zz Taken',
        '--name' => 'ZZ_TAKEN',
        '--icon' => 'cube',
        '--module' => true,
    ])
        ->expectsOutput('Module directory already exists: app-modules/zz-taken')
        ->assertExitCode(1);

    expect(File::exists(base_path('app-modules/zz-taken/composer.json')))->toBeFalse();
});

it('refuses module mode when the derived tenant app already exists', function (): void {
    TenantApp::create([
        'title' => 'Taken',
        'name' => 'ZZ-EXISTING',
        'icon' => 'heroicon:outline:cube',
        'route' => 'zz-existing',
        'is_active' => true,
    ]);

    $this->artisan('noerd:make-app', [
        '--title' => 'Zz Existing',
        '--name' => 'ZZ_EXISTING',
        '--icon' => 'cube',
        '--module' => true,
    ])
        ->expectsOutput("App with name 'ZZ-EXISTING' already exists.")
        ->assertExitCode(1);

    expect(File::isDirectory(base_path('app-modules/zz-existing')))->toBeFalse();
});
