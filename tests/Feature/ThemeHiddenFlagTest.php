<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Enums\Profile;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Services\ThemeRegistry;
use Noerd\Support\ThemeDefinition;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 | The `hidden` theme flag: internal themes (like the built-in settings-page
 | theme) are discovered like any other theme but never offered as a
 | tenant-wide form theme in the system settings picker.
 */

it('maps the hidden flag from theme.yml data', function (): void {
    expect(ThemeDefinition::fromArray('zz', ['hidden' => true])->hidden)->toBeTrue()
        ->and(ThemeDefinition::fromArray('zz', [])->hidden)->toBeFalse();
});

it('discovers the built-in settings theme as hidden with stacked full-width rows', function (): void {
    $definition = app(ThemeRegistry::class)->get('settings');

    expect($definition->name)->toBe('settings')
        ->and($definition->hidden)->toBeTrue()
        ->and($definition->fullWidthRows)->toBeTrue();
});

it('excludes hidden themes from the system settings theme picker', function (): void {
    $user = NoerdUser::factory()->create();
    $tenant = Tenant::factory()->create();
    // system-settings-page is admin-only and enforces it on mount.
    $user->tenants()->attach($tenant->id, ['profile_key' => Profile::Admin->value]);
    TenantHelper::setSelectedTenantId($tenant->id);
    $this->actingAs($user);

    $options = Livewire::test('noerd::system-settings-page')
        ->instance()
        ->themeOptions();

    expect($options)->toHaveKey('default')
        ->and($options)->not->toHaveKey('settings');
});
