<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Models\TenantApp;
use Noerd\Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

it('successfully creates a app with all parameters', function (): void {
    $this->artisan('noerd:create-app', [
        '--title' => 'Test Application',
        '--name' => 'TEST_APP',
        '--icon' => 'icons.test',
        '--route' => 'test.dashboard',
        '--active' => '1',
    ])
        ->expectsOutput('✅ Tenant app created successfully!')
        ->expectsOutputToContain('Test Application')
        ->expectsOutputToContain('TEST_APP')
        ->expectsOutputToContain('icons.test')
        ->expectsOutputToContain('test.dashboard')
        ->expectsOutputToContain('Yes')
        ->expectsOutput('Run "php artisan noerd:assign-apps-to-tenant" to assign this app to a tenant.')
        ->assertExitCode(0);

    // Verify the app was created in the database
    expect(TenantApp::where('name', 'TEST_APP')->exists())->toBeTrue();

    $app = TenantApp::where('name', 'TEST_APP')->first();
    expect($app->title)->toBe('Test Application');
    expect($app->icon)->toBe('icons.test');
    expect($app->route)->toBe('test.dashboard');
    expect($app->is_active)->toBeTrue();
});

it('creates an inactive tenant app when active is set to 0', function (): void {
    $this->artisan('noerd:create-app', [
        '--title' => 'Inactive App',
        '--name' => 'INACTIVE_APP',
        '--icon' => 'icons.inactive',
        '--route' => 'inactive.dashboard',
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
        '--route' => 'default.dashboard',
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
        '--route' => '',
    ])
        ->expectsOutput('All fields (title, name, icon, route) are required.')
        ->assertExitCode(1);

    // Verify no new app was created
    expect(TenantApp::count())->toBe($appCountBefore);
});

it('fails when only some fields are provided', function (): void {
    $this->artisan('noerd:create-app', [
        '--title' => 'Test Title',
        '--name' => 'MISSING_FIELDS',
        '--icon' => 'icons.test',
        '--route' => '', // Missing route
    ])
        ->expectsOutput('All fields (title, name, icon, route) are required.')
        ->assertExitCode(1);
});

it('normalizes the app name to the uppercase underscore form', function (string $input, string $expected): void {
    $this->artisan('noerd:create-app', [
        '--title' => 'Normalized App',
        '--name' => $input,
        '--icon' => 'icons.test',
        '--route' => 'test.route',
    ])
        ->expectsOutput('✅ Tenant app created successfully!')
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
        '--route' => 'test.route',
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
        '--route' => 'duplicate.route',
    ])
        ->expectsOutput("App with name 'EXISTING_APP' already exists.")
        ->assertExitCode(1);
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
        '--route' => 'noerd-app-a.duplicate',
    ])
        ->expectsOutput("App with name 'NOERD_APP_A' already exists.")
        ->assertExitCode(1);
});

it('displays comprehensive app details in output table', function (): void {
    $this->artisan('noerd:create-app', [
        '--title' => 'Complete Details App',
        '--name' => 'DETAILS_APP',
        '--icon' => 'icons.details',
        '--route' => 'details.dashboard',
    ])
        ->expectsOutput('✅ Tenant app created successfully!')
        ->expectsOutputToContain('| ID      |')
        ->expectsOutputToContain('| Title   | Complete Details App')
        ->expectsOutputToContain('| Name    | DETAILS_APP')
        ->expectsOutputToContain('| Icon    | icons.details')
        ->expectsOutputToContain('| Route   | details.dashboard')
        ->expectsOutputToContain('| Active  | Yes')
        ->expectsOutputToContain('| Created |')
        ->assertExitCode(0);
});
