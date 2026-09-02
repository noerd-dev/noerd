<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Contracts\SetupCollectionDefinitionRepositoryContract;
use Noerd\Enums\Profile;
use Noerd\Helpers\SetupCollectionHelper;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\SetupCollection;
use Noerd\Models\SetupCollectionEntry;
use Noerd\Models\Tenant;
use Noerd\Services\SetupFieldTypeConverter;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    // Force yaml mode so the helper reads from the temporary example.yml
    // created below, rather than the database-backed repository.
    config(['noerd.collections.mode' => 'yaml']);
    config(['noerd.collections.show_definitions_ui' => false]);
    app()->forgetInstance(SetupCollectionDefinitionRepositoryContract::class);
    app()->forgetInstance(SetupCollectionHelper::class);

    // Create a tenant and user for testing (Tenant::created seeds default languages)
    $this->tenant = Tenant::factory()->create();

    $this->user = NoerdUser::factory()->create();

    // Attach user to tenant with admin profile
    $this->user->tenants()->attach($this->tenant->id, ['profile_key' => Profile::Admin->value]);

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
  - name: detailData.title
    label: Title
    type: translatableText
    colspan: 6
  - name: detailData.description
    label: Description
    type: translatableTextarea
    colspan: 6
  - name: detailData.is_active
    label: Active
    type: checkbox
    colspan: 3
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

describe('SetupCollectionHelper', function (): void {
    it('returns null for non-existent collection', function (): void {
        $result = SetupCollectionHelper::getCollectionFields('non-existent');

        expect($result)->toBeNull();
    });

    it('loads collection fields from YAML file', function (): void {
        $result = SetupCollectionHelper::getCollectionFields('example');

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('title')
            ->and($result)->toHaveKey('fields')
            ->and($result['title'])->toBe('Beispiel');
    });

    it('returns all available collections', function (): void {
        $collections = SetupCollectionHelper::getAllCollections();

        expect($collections)->toBeArray()
            ->and(count($collections))->toBeGreaterThan(0);

        $exampleCollection = collect($collections)->firstWhere('key', 'example');
        expect($exampleCollection)->not->toBeNull()
            ->and($exampleCollection['titleList'])->toBe('Beispiele');
    });
});

describe('SetupCollectionHelper select options', function (): void {
    beforeEach(function (): void {
        $collection = SetupCollection::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'collection_key' => 'ZZ_OPTIONS_TEST'],
            ['name' => 'Options Test', 'sort' => 0],
        );

        SetupCollectionEntry::create([
            'tenant_id' => $this->tenant->id,
            'setup_collection_id' => $collection->id,
            'data' => ['name' => ['de' => 'Deutschland', 'en' => 'Germany'], 'code' => 'DE'],
            'sort' => 0,
        ]);
    });

    it('builds value-field options with translated labels', function (): void {
        session(['selectedLanguage' => 'de']);

        $options = SetupCollectionHelper::selectOptions('ZZ_OPTIONS_TEST', 'name', 'code');

        expect($options)->toHaveCount(1);
        expect($options[0]['value'])->toBe('DE');
        expect($options[0]['label'])->toBe('Deutschland');
    });

    it('falls back to the entry id without a value field', function (): void {
        $options = SetupCollectionHelper::selectOptions('ZZ_OPTIONS_TEST');

        expect($options[0]['value'])->toBeInt();
    });

    it('returns an empty set for an unknown collection', function (): void {
        expect(SetupCollectionHelper::selectOptions('ZZ_DOES_NOT_EXIST', 'name', 'code'))->toBe([]);
    });
});

describe('SetupCollection Model', function (): void {
    it('can create a setup collection', function (): void {
        $collection = SetupCollection::create([
            'tenant_id' => $this->tenant->id,
            'collection_key' => 'TEST',
            'name' => 'Test Collection',
        ]);

        expect($collection->exists)->toBeTrue()
            ->and($collection->collection_key)->toBe('TEST');
    });

    it('has entries relationship', function (): void {
        $collection = SetupCollection::create([
            'tenant_id' => $this->tenant->id,
            'collection_key' => 'TEST',
            'name' => 'Test Collection',
        ]);

        $entry = SetupCollectionEntry::create([
            'tenant_id' => $this->tenant->id,
            'setup_collection_id' => $collection->id,
            'data' => ['title' => ['de' => 'Test', 'en' => 'Test']],
        ]);

        expect($collection->entries)->toHaveCount(1)
            ->and($collection->entries->first()->id)->toBe($entry->id);
    });
});

describe('SetupCollectionEntry Model', function (): void {
    it('can create an entry with JSON data', function (): void {
        $collection = SetupCollection::create([
            'tenant_id' => $this->tenant->id,
            'collection_key' => 'TEST',
            'name' => 'Test Collection',
        ]);

        $entry = SetupCollectionEntry::create([
            'tenant_id' => $this->tenant->id,
            'setup_collection_id' => $collection->id,
            'data' => [
                'title' => ['de' => 'Deutscher Titel', 'en' => 'English Title'],
                'is_active' => true,
            ],
            'sort' => 1,
        ]);

        expect($entry->exists)->toBeTrue()
            ->and($entry->data)->toBeArray()
            ->and($entry->data['title']['de'])->toBe('Deutscher Titel')
            ->and($entry->sort)->toBe(1);
    });

    it('belongs to a collection', function (): void {
        $collection = SetupCollection::create([
            'tenant_id' => $this->tenant->id,
            'collection_key' => 'TEST',
            'name' => 'Test Collection',
        ]);

        $entry = SetupCollectionEntry::create([
            'tenant_id' => $this->tenant->id,
            'setup_collection_id' => $collection->id,
            'data' => ['title' => 'Test'],
        ]);

        expect($entry->collection->id)->toBe($collection->id);
    });
});

describe('Setup Collections Route', function (): void {
    it('can access setup-collections route', function (): void {
        $response = $this->get('/setup/collections?key=example');

        $response->assertStatus(200);
    });
});

describe('Setup Collections List Component', function (): void {
    it('shows collection entries list', function (): void {
        Livewire::test('noerd::setup-collections-list', ['collectionKey' => 'example'])
            ->assertStatus(200)
            ->assertSee('Beispiele');
    });

    it('renders an empty state with the primary action when there are no entries', function (): void {
        app()->setLocale('en');

        $html = Livewire::test('noerd::setup-collections-list', ['collectionKey' => 'example'])->html();

        expect($html)
            // Empty hint shown below the table header (unique to the empty state).
            ->toContain('No entries yet')
            // The list's primary action is offered as a create button.
            ->toContain('listAction(null,');
    });
});

describe('Setup Collection Detail Component', function (): void {
    it('loads collection layout', function (): void {
        Livewire::test('noerd::setup-collection-detail', ['collectionKey' => 'example'])
            ->assertStatus(200)
            ->assertSet('collectionKey', 'example')
            ->assertSet('collectionLayout', fn($layout) => $layout !== null);
    });

    it('can save a new entry', function (): void {
        $entriesBefore = SetupCollectionEntry::where('tenant_id', $this->tenant->id)->count();

        $component = Livewire::test('noerd::setup-collection-detail', ['collectionKey' => 'example'])
            ->set('detailData.title.de', 'Test Titel')
            ->set('detailData.title.en', 'Test Title')
            ->set('detailData.is_active', true)
            ->call('store');

        $component->assertSet('showSuccessIndicator', true);

        expect(SetupCollectionEntry::where('tenant_id', $this->tenant->id)->count())->toBe($entriesBefore + 1);
    });
});

describe('SetupFieldTypeConverter', function (): void {
    it('converts string to translatable format', function (): void {
        $reflection = new ReflectionClass(SetupFieldTypeConverter::class);
        $method = $reflection->getMethod('convertToTranslatableField');
        $method->setAccessible(true);

        $result = $method->invoke(null, 'Test String');

        expect($result)->toBeArray()
            ->and($result['de'])->toBe('Test String')
            ->and($result['en'])->toBe('Test String');
    });

    it('keeps translatable format unchanged', function (): void {
        $reflection = new ReflectionClass(SetupFieldTypeConverter::class);
        $method = $reflection->getMethod('convertToTranslatableField');
        $method->setAccessible(true);

        $input = ['de' => 'Deutsch', 'en' => 'English'];
        $result = $method->invoke(null, $input);

        expect($result)->toBe($input);
    });

    it('extracts the default-language value from a translatable array', function (): void {
        $reflection = new ReflectionClass(SetupFieldTypeConverter::class);
        $method = $reflection->getMethod('convertFromTranslatableField');
        $method->setAccessible(true);

        // The tenant's default language wins — 'en' in the testbench setup.
        $result = $method->invoke(null, ['de' => 'Deutscher Text', 'en' => 'English Text']);

        expect($result)->toBe('English Text');

        // Without a value for the default language the first entry is used.
        expect($method->invoke(null, ['fr' => 'Texte français']))->toBe('Texte français');
    });

    it('returns unchanged data for non-existent collection', function (): void {
        $data = ['title' => 'Test', 'description' => 'Description'];
        $result = SetupFieldTypeConverter::convertCollectionData($data, 'non-existent-collection');

        expect($result)->toBe($data);
    });
});
