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
 * component, route, navigation). Snapshot what is there before each test and
 * remove everything the test generated afterwards.
 */
beforeEach(function (): void {
    $routeFile = base_path('routes/web.php');

    $this->routeFileExisted = File::exists($routeFile);
    $this->originalRoutes = $this->routeFileExisted ? File::get($routeFile) : null;
    $this->existingDashboards = File::glob(base_path('resources/views/components/*-dashboard.blade.php'));
    $this->existingAppConfigs = File::directories(base_path('app-configs'));
});

afterEach(function (): void {
    $routeFile = base_path('routes/web.php');

    if ($this->routeFileExisted) {
        File::put($routeFile, $this->originalRoutes);
    } else {
        File::delete($routeFile);
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
});

it('successfully creates a app with all parameters', function (): void {
    $this->artisan('noerd:create-app', [
        '--title' => 'Test Application',
        '--name' => 'TEST_APP',
        '--icon' => 'icons.test',
        '--active' => '1',
    ])
        ->expectsOutput('Tenant app created successfully!')
        ->expectsOutputToContain('Test Application')
        ->expectsOutputToContain('TEST_APP')
        ->expectsOutputToContain('icons.test')
        ->expectsOutputToContain('test_app.dashboard')
        ->expectsOutputToContain('Yes')
        ->expectsOutput('Run "php artisan noerd:assign-apps-to-tenant" to assign this app to a tenant.')
        ->assertExitCode(0);

    // Verify the app was created in the database
    expect(TenantApp::where('name', 'TEST_APP')->exists())->toBeTrue();

    $app = TenantApp::where('name', 'TEST_APP')->first();
    expect($app->title)->toBe('Test Application');
    expect($app->icon)->toBe('icons.test');
    expect($app->route)->toBe('test_app.dashboard');
    expect($app->is_active)->toBeTrue();
});

it('scaffolds a dashboard for the new app and uses its route without asking', function (): void {
    $this->artisan('noerd:create-app', [
        '--title' => 'Time Booking',
        '--name' => 'TB',
        '--icon' => 'icons.tb',
    ])
        ->expectsOutput('Dashboard files created successfully!')
        ->expectsOutput('Tenant app created successfully!')
        ->assertExitCode(0);

    expect(TenantApp::where('name', 'TB')->value('route'))->toBe('tb.dashboard');

    $dashboard = base_path('resources/views/components/tb-dashboard.blade.php');
    $stub = File::get(dirname(__DIR__, 2) . '/src/Commands/stubs/resource/dashboard.blade.stub');
    expect(File::exists($dashboard))->toBeTrue()
        ->and(File::get($dashboard))->toBe(str_replace('{{appName}}', 'tb', $stub));

    $routes = File::get(base_path('routes/web.php'));
    expect($routes)
        ->toContain("Route::livewire('tb', 'tb-dashboard')->name('tb.dashboard');")
        ->toContain("Route::middleware(['noerd', 'app-access:tb'])");

    $navigation = base_path('app-configs/tb/navigation.yml');
    expect(File::exists($navigation))->toBeTrue();

    $parsed = Symfony\Component\Yaml\Yaml::parse(File::get($navigation));
    expect($parsed[0]['title'])->toBe('Time Booking')
        ->and($parsed[0]['name'])->toBe('tb')
        ->and($parsed[0]['route'])->toBe('tb.dashboard')
        ->and($parsed[0]['block_menus'][0]['navigations'][0]['route'])->toBe('tb.dashboard');
});

it('creates the routes file when the project has none yet', function (): void {
    File::delete(base_path('routes/web.php'));

    $this->artisan('noerd:create-app', [
        '--title' => 'Fresh App',
        '--name' => 'FRESH',
        '--icon' => 'icons.fresh',
    ])->assertExitCode(0);

    $routes = File::get(base_path('routes/web.php'));
    expect($routes)
        ->toStartWith("<?php\n")
        ->toContain("->name('fresh.dashboard');");
});

it('keeps an existing navigation and only inserts the dashboard entry', function (): void {
    File::ensureDirectoryExists(base_path('app-configs/existing_nav'));
    File::put(base_path('app-configs/existing_nav/navigation.yml'), implode("\n", [
        '- title: Existing',
        '  name: existing_nav',
        '  route: existing_nav.dashboard',
        '  block_menus:',
        '    - title: Overview',
        '      navigations:',
        '        - title: Things',
        '          route: existing_nav.things',
        '          heroicon: cube',
        '',
    ]));

    $this->artisan('noerd:create-app', [
        '--title' => 'Existing',
        '--name' => 'EXISTING_NAV',
        '--icon' => 'icons.existing',
    ])->assertExitCode(0);

    $parsed = Symfony\Component\Yaml\Yaml::parse(File::get(base_path('app-configs/existing_nav/navigation.yml')));
    expect($parsed[0]['block_menus'][0]['navigations'][0]['route'])->toBe('existing_nav.things');
});

it('points the app at an explicit route and skips the dashboard scaffold', function (): void {
    $this->artisan('noerd:create-app', [
        '--title' => 'Custom Route App',
        '--name' => 'CUSTOM_ROUTE',
        '--icon' => 'icons.custom',
        '--route' => 'custom.index',
    ])
        ->doesntExpectOutput('Dashboard files created successfully!')
        ->expectsOutputToContain('custom.index')
        ->assertExitCode(0);

    expect(TenantApp::where('name', 'CUSTOM_ROUTE')->value('route'))->toBe('custom.index')
        ->and(File::exists(base_path('resources/views/components/custom_route-dashboard.blade.php')))->toBeFalse()
        ->and(File::exists(base_path('app-configs/custom_route/navigation.yml')))->toBeFalse();
});

it('creates an inactive tenant app when active is set to 0', function (): void {
    $this->artisan('noerd:create-app', [
        '--title' => 'Inactive App',
        '--name' => 'INACTIVE_APP',
        '--icon' => 'icons.inactive',
        '--active' => '0',
    ])
        ->expectsOutputToContain('No')
        ->assertExitCode(0);

    $app = TenantApp::where('name', 'INACTIVE_APP')->first();
    expect($app->is_active)->toBeFalse();
});

it('defaults to active when active parameter is not provided', function (): void {
    $this->artisan('noerd:create-app', [
        '--title' => 'Default Active App',
        '--name' => 'DEFAULT_ACTIVE',
        '--icon' => 'icons.default',
    ])
        ->expectsOutputToContain('Yes')
        ->assertExitCode(0);

    $app = TenantApp::where('name', 'DEFAULT_ACTIVE')->first();
    expect($app->is_active)->toBeTrue();
});

it('fails when required fields are missing', function (): void {

    $appCountBefore = TenantApp::count();
    $this->artisan('noerd:create-app', [
        '--title' => '',
        '--name' => '',
        '--icon' => '',
    ])
        ->expectsOutput('All fields (title, name, icon) are required.')
        ->assertExitCode(1);

    // Verify no new app was created
    expect(TenantApp::count())->toBe($appCountBefore);
});

it('fails when only some fields are provided', function (): void {
    $this->artisan('noerd:create-app', [
        '--title' => 'Test Title',
        '--name' => 'MISSING_FIELDS',
        '--icon' => '', // Missing icon
    ])
        ->expectsOutput('All fields (title, name, icon) are required.')
        ->assertExitCode(1);
});

it('normalizes the app name to the uppercase underscore form', function (string $input, string $expected): void {
    $this->artisan('noerd:create-app', [
        '--title' => 'Normalized App',
        '--name' => $input,
        '--icon' => 'icons.test',
    ])
        ->expectsOutput('Tenant app created successfully!')
        ->assertExitCode(0);

    expect(TenantApp::where('name', $expected)->exists())->toBeTrue();
})->with([
    'lowercase with space' => ['lowercase app', 'LOWERCASE_APP'],
    'hyphens' => ['hyphen-name', 'HYPHEN_NAME'],
    'uppercase with space' => ['SPACED NAME', 'SPACED_NAME'],
    'underscores kept' => ['UNDERSCORE_NAME_APP', 'UNDERSCORE_NAME_APP'],
    'single word' => ['SINGLE', 'SINGLE'],
]);

it('fails when name contains special characters', function (): void {
    $this->artisan('noerd:create-app', [
        '--title' => 'Special Chars App',
        '--name' => 'SPECIAL-CHARS!',
        '--icon' => 'icons.test',
    ])
        ->expectsOutput('App name must contain only uppercase letters and underscores (e.g., CMS, MEDIA, MY_APP).')
        ->assertExitCode(1);
});

it('fails when app name already exists', function (): void {
    // First, create an app
    TenantApp::create([
        'title' => 'Existing App',
        'name' => 'EXISTING_APP',
        'icon' => 'icons.existing',
        'route' => 'existing.route',
        'is_active' => true,
    ]);

    // Try to create another app with the same name
    $this->artisan('noerd:create-app', [
        '--title' => 'Duplicate App',
        '--name' => 'EXISTING_APP',
        '--icon' => 'icons.duplicate',
    ])
        ->expectsOutput("App with name 'EXISTING_APP' already exists.")
        ->assertExitCode(1);

    expect(File::exists(base_path('resources/views/components/existing_app-dashboard.blade.php')))->toBeFalse();
});

it('fails when app name conflicts with seeded data', function (): void {
    // Create an app that conflicts with existing seeded app name
    TenantApp::create([
        'title' => 'Noerd App A Duplicate',
        'name' => 'NOERD_APP_A',
        'icon' => 'icons.noerd-app-a',
        'route' => 'noerd-app-a.duplicate',
        'is_active' => true,
    ]);

    // Try to create an app with name that exists in test data (from TestCase setUp)
    $this->artisan('noerd:create-app', [
        '--title' => 'Noerd App A Duplicate',
        '--name' => 'NOERD_APP_A',
        '--icon' => 'icons.noerd-app-a',
    ])
        ->expectsOutput("App with name 'NOERD_APP_A' already exists.")
        ->assertExitCode(1);
});

it('displays comprehensive app details in output table', function (): void {
    $this->artisan('noerd:create-app', [
        '--title' => 'Complete Details App',
        '--name' => 'DETAILS_APP',
        '--icon' => 'icons.details',
    ])
        ->expectsOutput('Tenant app created successfully!')
        ->expectsOutputToContain('| ID      |')
        ->expectsOutputToContain('| Title   | Complete Details App')
        ->expectsOutputToContain('| Name    | DETAILS_APP')
        ->expectsOutputToContain('| Icon    | icons.details')
        ->expectsOutputToContain('| Route   | details_app.dashboard')
        ->expectsOutputToContain('| Active  | Yes')
        ->expectsOutputToContain('| Created |')
        ->assertExitCode(0);
});
