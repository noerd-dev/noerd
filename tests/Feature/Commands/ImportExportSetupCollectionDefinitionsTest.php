<?php

use Noerd\Models\SetupCollection;
use Noerd\Models\SetupCollectionDefinition;
use Noerd\Repositories\DatabaseSetupCollectionDefinitionRepository;
use Noerd\Tests\Traits\CreatesSetupUser;
use Symfony\Component\Yaml\Yaml;

uses(Tests\TestCase::class);
uses(CreatesSetupUser::class);

beforeEach(function (): void {
    DatabaseSetupCollectionDefinitionRepository::resetCache();

    // Use a test-only directory inside base_path so base_path() resolution works.
    $this->relativeYamlPath = 'storage/app/test-setup-collections-' . uniqid();
    $this->absoluteYamlPath = base_path($this->relativeYamlPath);
    if (! is_dir($this->absoluteYamlPath)) {
        mkdir($this->absoluteYamlPath, 0755, true);
    }

    config(['noerd.collections.setup_yaml_path' => $this->relativeYamlPath]);
});

afterEach(function (): void {
    if (isset($this->absoluteYamlPath) && is_dir($this->absoluteYamlPath)) {
        foreach (glob($this->absoluteYamlPath . '/*.yml') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->absoluteYamlPath);
    }
});

it('imports a YAML definition into the database for a specific tenant', function (): void {
    ['tenant' => $tenant] = $this->createUserWithSetupAccess();

    file_put_contents($this->absoluteYamlPath . '/expense_categories.yml', Yaml::dump([
        'title' => 'Ausgabenkategorie',
        'titleList' => 'Ausgabenkategorien',
        'key' => 'EXPENSE_CATEGORIES',
        'description' => '',
        'fields' => [
            ['name' => 'detailData.name', 'label' => 'Name', 'type' => 'text', 'colspan' => 6],
        ],
    ]));

    $this->artisan('noerd:setup-collections:import-yaml', ['--tenant-id' => $tenant->id])
        ->assertSuccessful();

    expect(SetupCollectionDefinition::where('tenant_id', $tenant->id)->where('filename', 'expense_categories')->exists())->toBeTrue();
    expect(SetupCollection::where('tenant_id', $tenant->id)->where('collection_key', 'EXPENSE_CATEGORIES')->exists())->toBeTrue();
});

it('imports for every tenant with --all-tenants', function (): void {
    ['tenant' => $tenantA] = $this->createUserWithSetupAccess();
    $tenantB = \Noerd\Models\Tenant::factory()->create();

    file_put_contents($this->absoluteYamlPath . '/expense_categories.yml', Yaml::dump([
        'title' => 'Ausgabenkategorie',
        'titleList' => 'Ausgabenkategorien',
        'key' => 'EXPENSE_CATEGORIES',
        'fields' => [],
    ]));

    $this->artisan('noerd:setup-collections:import-yaml', ['--all-tenants' => true])
        ->assertSuccessful();

    expect(SetupCollectionDefinition::where('tenant_id', $tenantA->id)->where('filename', 'expense_categories')->exists())->toBeTrue();
    expect(SetupCollectionDefinition::where('tenant_id', $tenantB->id)->where('filename', 'expense_categories')->exists())->toBeTrue();
});

it('supports dry-run without writing', function (): void {
    ['tenant' => $tenant] = $this->createUserWithSetupAccess();

    file_put_contents($this->absoluteYamlPath . '/expense_categories.yml', Yaml::dump([
        'title' => 'Ausgabenkategorie',
        'titleList' => 'Ausgabenkategorien',
        'key' => 'EXPENSE_CATEGORIES',
        'description' => '',
        'fields' => [],
    ]));

    $this->artisan('noerd:setup-collections:import-yaml', [
        '--tenant-id' => $tenant->id,
        '--dry-run' => true,
    ])->assertSuccessful();

    expect(SetupCollectionDefinition::where('tenant_id', $tenant->id)->count())->toBe(0);
});

it('deletes source YAML files after import with --delete flag', function (): void {
    ['tenant' => $tenant] = $this->createUserWithSetupAccess();

    file_put_contents($this->absoluteYamlPath . '/expense_categories.yml', Yaml::dump([
        'title' => 'Ausgabenkategorie',
        'titleList' => 'Ausgabenkategorien',
        'key' => 'EXPENSE_CATEGORIES',
        'fields' => [],
    ]));

    $this->artisan('noerd:setup-collections:import-yaml', [
        '--tenant-id' => $tenant->id,
        '--delete' => true,
    ])->assertSuccessful();

    expect(file_exists($this->absoluteYamlPath . '/expense_categories.yml'))->toBeFalse();
    expect(SetupCollectionDefinition::where('tenant_id', $tenant->id)->where('filename', 'expense_categories')->exists())->toBeTrue();
});

it('exports database definitions to YAML files', function (): void {
    ['tenant' => $tenant] = $this->createUserWithSetupAccess();

    SetupCollectionDefinition::create([
        'tenant_id' => $tenant->id,
        'filename' => 'expense_categories',
        'key' => 'EXPENSE_CATEGORIES',
        'title' => 'Ausgabenkategorie',
        'title_list' => 'Ausgabenkategorien',
        'description' => '',
        'fields' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'colspan' => 6],
        ],
    ]);

    $this->artisan('noerd:setup-collections:export-yaml', ['--tenant-id' => $tenant->id])
        ->assertSuccessful();

    $exportedFile = $this->absoluteYamlPath . '/expense_categories.yml';
    expect(file_exists($exportedFile))->toBeTrue();

    $content = Yaml::parseFile($exportedFile);
    expect($content['key'])->toBe('EXPENSE_CATEGORIES');
    expect($content['fields'][0]['name'])->toBe('detailData.name');
});

it('refuses to overwrite existing YAML files without --force', function (): void {
    ['tenant' => $tenant] = $this->createUserWithSetupAccess();

    SetupCollectionDefinition::create([
        'tenant_id' => $tenant->id,
        'filename' => 'expense_categories',
        'key' => 'EXPENSE_CATEGORIES',
        'title' => 'Ausgabenkategorie neu',
        'title_list' => 'Ausgabenkategorien',
        'fields' => [],
    ]);

    file_put_contents($this->absoluteYamlPath . '/expense_categories.yml', Yaml::dump([
        'title' => 'OldTitle',
        'fields' => [],
    ]));

    $this->artisan('noerd:setup-collections:export-yaml', ['--tenant-id' => $tenant->id])
        ->assertSuccessful();

    $content = Yaml::parseFile($this->absoluteYamlPath . '/expense_categories.yml');
    expect($content['title'])->toBe('OldTitle');

    $this->artisan('noerd:setup-collections:export-yaml', [
        '--tenant-id' => $tenant->id,
        '--force' => true,
    ])->assertSuccessful();

    $content = Yaml::parseFile($this->absoluteYamlPath . '/expense_categories.yml');
    expect($content['title'])->toBe('Ausgabenkategorie neu');
});
