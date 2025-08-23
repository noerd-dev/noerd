<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Noerd\Models\TenantApp;

uses(Tests\TestCase::class);
uses(RefreshDatabase::class);

it('successfully creates a tenant app with all parameters', function (): void {
    $this->artisan('noerd:create-tenant-app', [
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
        ->expectsOutput('The app can now be assigned to tenants through the tenant management system.')
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
    $this->artisan('noerd:create-tenant-app', [
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
    $this->artisan('noerd:create-tenant-app', [
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
    $this->artisan('noerd:create-tenant-app', [
        '--title' => '',
        '--name' => '',
        '--icon' => '',
        '--route' => '',
    ])
        ->expectsOutput('All fields (title, name, icon, route) are required.')
        ->assertExitCode(1);

    // Verify no new app was created (only seeded ones exist)
    expect(TenantApp::count())->toBe(13);
});

it('fails when only some fields are provided', function (): void {
    $this->artisan('noerd:create-tenant-app', [
        '--title' => 'Test Title',
        '--name' => 'MISSING_FIELDS',
        '--icon' => 'icons.test',
        '--route' => '', // Missing route
    ])
        ->expectsOutput('All fields (title, name, icon, route) are required.')
        ->assertExitCode(1);
});

it('fails when name has invalid format with lowercase', function (): void {
    $this->artisan('noerd:create-tenant-app', [
        '--title' => 'Invalid Name App',
        '--name' => 'invalid-name',
        '--icon' => 'icons.test',
        '--route' => 'test.route',
    ])
        ->expectsOutput('App name must contain only uppercase letters and underscores (e.g., CMS, MEDIA, MY_APP).')
        ->assertExitCode(1);
});

it('fails when name contains spaces', function (): void {
    $this->artisan('noerd:create-tenant-app', [
        '--title' => 'Spaced Name App',
        '--name' => 'SPACED NAME',
        '--icon' => 'icons.test',
        '--route' => 'test.route',
    ])
        ->expectsOutput('App name must contain only uppercase letters and underscores (e.g., CMS, MEDIA, MY_APP).')
        ->assertExitCode(1);
});

it('fails when name contains special characters', function (): void {
    $this->artisan('noerd:create-tenant-app', [
        '--title' => 'Special Chars App',
        '--name' => 'SPECIAL-CHARS!',
        '--icon' => 'icons.test',
        '--route' => 'test.route',
    ])
        ->expectsOutput('App name must contain only uppercase letters and underscores (e.g., CMS, MEDIA, MY_APP).')
        ->assertExitCode(1);
});

it('accepts name with underscores', function (): void {
    $this->artisan('noerd:create-tenant-app', [
        '--title' => 'Underscore Name App',
        '--name' => 'UNDERSCORE_NAME_APP',
        '--icon' => 'icons.test',
        '--route' => 'test.route',
    ])
        ->expectsOutput('✅ Tenant app created successfully!')
        ->assertExitCode(0);

    expect(TenantApp::where('name', 'UNDERSCORE_NAME_APP')->exists())->toBeTrue();
});

it('accepts single word uppercase names', function (): void {
    $this->artisan('noerd:create-tenant-app', [
        '--title' => 'Single Word App',
        '--name' => 'SINGLE',
        '--icon' => 'icons.single',
        '--route' => 'single.route',
    ])
        ->assertExitCode(0);

    expect(TenantApp::where('name', 'SINGLE')->exists())->toBeTrue();
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
    $this->artisan('noerd:create-tenant-app', [
        '--title' => 'Duplicate App',
        '--name' => 'EXISTING_APP',
        '--icon' => 'icons.duplicate',
        '--route' => 'duplicate.route',
    ])
        ->expectsOutput("App with name 'EXISTING_APP' already exists.")
        ->assertExitCode(1);
});

it('fails when app name conflicts with seeded data', function (): void {
    // Try to create an app with name that exists in test data (from TestCase setUp)
    $this->artisan('noerd:create-tenant-app', [
        '--title' => 'CMS Duplicate',
        '--name' => 'CMS',
        '--icon' => 'icons.cms',
        '--route' => 'cms.duplicate',
    ])
        ->expectsOutput("App with name 'CMS' already exists.")
        ->assertExitCode(1);
});

it('displays comprehensive app details in output table', function (): void {
    $this->artisan('noerd:create-tenant-app', [
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

it('handles titles with special characters correctly', function (): void {
    $this->artisan('noerd:create-tenant-app', [
        '--title' => 'Special Chars App (Test & Demo)',
        '--name' => 'SPECIAL_CHARS',
        '--icon' => 'icons.special',
        '--route' => 'special.route',
    ])
        ->assertExitCode(0);

    $app = TenantApp::where('name', 'SPECIAL_CHARS')->first();
    expect($app->title)->toBe('Special Chars App (Test & Demo)');
});

it('handles long titles correctly', function (): void {
    $longTitle = 'This is a very long title that should still be handled correctly by the command';

    $this->artisan('noerd:create-tenant-app', [
        '--title' => $longTitle,
        '--name' => 'LONG_TITLE',
        '--icon' => 'icons.long',
        '--route' => 'long.route',
    ])
        ->assertExitCode(0);

    $app = TenantApp::where('name', 'LONG_TITLE')->first();
    expect($app->title)->toBe($longTitle);
});

it('handles complex route names correctly', function (): void {
    $this->artisan('noerd:create-tenant-app', [
        '--title' => 'Complex Route App',
        '--name' => 'COMPLEX_ROUTE',
        '--icon' => 'icons.complex',
        '--route' => 'admin.module.sub-module.dashboard',
    ])
        ->assertExitCode(0);

    $app = TenantApp::where('name', 'COMPLEX_ROUTE')->first();
    expect($app->route)->toBe('admin.module.sub-module.dashboard');
});

it('handles complex icon names correctly', function (): void {
    $this->artisan('noerd:create-tenant-app', [
        '--title' => 'Complex Icon App',
        '--name' => 'COMPLEX_ICON',
        '--icon' => 'heroicon-o-cog-6-tooth',
        '--route' => 'complex.route',
    ])
        ->assertExitCode(0);

    $app = TenantApp::where('name', 'COMPLEX_ICON')->first();
    expect($app->icon)->toBe('heroicon-o-cog-6-tooth');
});

it('provides correct help information', function (): void {
    $this->artisan('noerd:create-tenant-app', ['--help'])
        ->expectsOutputToContain('Create a new tenant app that can be assigned to tenants')
        ->expectsOutputToContain('--title')
        ->expectsOutputToContain('--name')
        ->expectsOutputToContain('--icon')
        ->expectsOutputToContain('--route')
        ->expectsOutputToContain('--active')
        ->assertExitCode(0);
});
