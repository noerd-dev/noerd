<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Models\User;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('throws exception for non-existing table config', function (): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('noerdApp')->create();
    $this->actingAs($user);

    StaticConfigHelper::getListConfig('___not_existing___');
})->throws(Exception::class, 'Config file not found');

it('loads table config for existing list', function (): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('noerdApp')->create();
    $this->actingAs($user);

    $config = StaticConfigHelper::getListConfig('user-roles-list');
    expect($config)->toBeArray()->and($config)->not->toBeEmpty();
});

it('throws exception for non-existing model config', function (): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('noerdApp')->create();
    $this->actingAs($user);

    StaticConfigHelper::getComponentFields('___not_existing___');
})->throws(Exception::class, 'Config file not found');

it('loads model config for existing component', function (): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('noerdApp')->create();
    $this->actingAs($user);

    $fields = StaticConfigHelper::getComponentFields('user-detail');
    expect($fields)->toBeArray()->and($fields)->not->toBeEmpty();
});

it('loads navigation structure for app', function (): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('setup')->create();
    $this->actingAs($user);

    $navigation = StaticConfigHelper::getNavigationStructure();
    expect($navigation)->toBeArray()->and($navigation)->not->toBeEmpty();
});

it('returns null for navigation when no app selected', function (): void {
    $user = User::factory()->withExampleTenant()->create();
    $this->actingAs($user);

    $navigation = StaticConfigHelper::getNavigationStructure();
    expect($navigation)->toBeNull();
});

it('hides navigation items when config value is false', function (): void {
    config()->set('noerd.features.roles', false);

    $user = User::factory()->withExampleTenant()->withSelectedApp('setup')->create();
    $this->actingAs($user);

    $navigation = StaticConfigHelper::getNavigationStructure();

    // Find user-roles in navigation
    $adminBlock = collect($navigation[0]['block_menus'])->firstWhere('title', 'noerd_nav_administration');
    $navTitles = collect($adminBlock['navigations'])->pluck('title')->all();

    expect($navTitles)->not->toContain('noerd_nav_user_roles');
});

it('shows navigation items when config value is true', function (): void {
    config()->set('noerd.features.roles', true);

    $user = User::factory()->withExampleTenant()->withSelectedApp('setup')->create();
    $this->actingAs($user);

    $navigation = StaticConfigHelper::getNavigationStructure();

    // Find user-roles in navigation
    $adminBlock = collect($navigation[0]['block_menus'])->firstWhere('title', 'noerd_nav_administration');
    $navTitles = collect($adminBlock['navigations'])->pluck('title')->all();

    expect($navTitles)->toContain('noerd_nav_user_roles');
});

it('shows superAdmin navigation items for super admins', function (): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('setup')->create([
        'super_admin' => true,
    ]);
    $this->actingAs($user);

    $navigation = StaticConfigHelper::getNavigationStructure();

    $adminBlock = collect($navigation[0]['block_menus'])->firstWhere('title', 'noerd_nav_administration');
    $navTitles = collect($adminBlock['navigations'])->pluck('title')->all();

    expect($navTitles)->toContain('noerd_nav_apps');
});

it('hides superAdmin navigation items for non-super admins', function (): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('setup')->create([
        'super_admin' => false,
    ]);
    $this->actingAs($user);

    $navigation = StaticConfigHelper::getNavigationStructure();

    $adminBlock = collect($navigation[0]['block_menus'])->firstWhere('title', 'noerd_nav_administration');
    $navTitles = collect($adminBlock['navigations'])->pluck('title')->all();

    expect($navTitles)->not->toContain('noerd_nav_apps');
});
