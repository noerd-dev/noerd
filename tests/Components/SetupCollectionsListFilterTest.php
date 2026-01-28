<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Noerd\Noerd\Helpers\TenantHelper;
use Noerd\Noerd\Models\Profile;
use Noerd\Noerd\Models\SetupLanguage;
use Noerd\Noerd\Models\Tenant;
use Noerd\Noerd\Models\User;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    // Ensure default languages exist
    SetupLanguage::ensureDefaultLanguages();

    // Create a tenant and user for testing
    $this->tenant = Tenant::factory()->create();

    // Create admin profile for the tenant
    $adminProfile = Profile::factory()->create([
        'tenant_id' => $this->tenant->id,
        'key' => 'ADMIN',
        'name' => 'Admin',
    ]);

    $this->user = User::factory()->create();

    // Attach user to tenant with admin profile
    $this->user->tenants()->attach($this->tenant->id, ['profile_id' => $adminProfile->id]);

    // Set tenant and app via session helper
    TenantHelper::setSelectedTenantId($this->tenant->id);
    TenantHelper::setSelectedApp('SETUP');

    $this->actingAs($this->user);

    // Create temporary example.yml for testing
    $this->exampleYamlPath = base_path('app-configs/setup/collections/example.yml');
    $exampleYamlContent = <<<'YAML'
title: 'Beispiel'
titleList: 'Beispiele'
key: 'EXAMPLE'
buttonList: 'Neuer Eintrag'
description: 'Eine Beispiel-Collection für Setup'
hasPage: false
fields:
  - { name: model.title, label: noerd_label_title, type: translatableText, colspan: 6 }
YAML;

    // Ensure directory exists
    if (! is_dir(dirname($this->exampleYamlPath))) {
        mkdir(dirname($this->exampleYamlPath), 0755, true);
    }
    file_put_contents($this->exampleYamlPath, $exampleYamlContent);
});

afterEach(function (): void {
    // Clean up temporary example.yml
    if (isset($this->exampleYamlPath) && file_exists($this->exampleYamlPath)) {
        unlink($this->exampleYamlPath);
        // Remove directory if empty
        $dir = dirname($this->exampleYamlPath);
        if (is_dir($dir) && count(scandir($dir)) === 2) {
            rmdir($dir);
        }
    }
});

it('can set activeListFilters without error', function (): void {
    Volt::test('setup-collections-list', ['collectionKey' => 'example'])
        ->set('activeListFilters.language', 'de')
        ->assertHasNoErrors();
});

it('applies language filter without error', function (): void {
    // Set session language to 'en' so that when rendering syncs from session,
    // it uses the same value we're setting
    session(['selectedLanguage' => 'en']);

    $component = Volt::test('setup-collections-list', ['collectionKey' => 'example'])
        ->set('activeListFilters.language', 'en');

    expect($component->get('activeListFilters')['language'])->toBe('en');
});
