<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Helpers\SetupCollectionHelper;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\Profile;
use Noerd\Models\SetupCollection;
use Noerd\Models\SetupCollectionEntry;
use Noerd\Models\SetupLanguage;
use Noerd\Models\Tenant;
use Noerd\Models\User;
use Noerd\Services\SetupFieldTypeConverter;

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
  - { name: model.description, label: noerd_label_description, type: translatableTextarea, colspan: 6 }
  - { name: model.is_active, label: noerd_label_active, type: checkbox, colspan: 3 }
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

describe('SetupLanguage Model', function (): void {
    it('creates default languages when none exist', function (): void {
        SetupLanguage::query()->delete();
        SetupLanguage::ensureDefaultLanguages();

        expect(SetupLanguage::count())->toBe(1);
        expect(SetupLanguage::where('code', 'en')->exists())->toBeTrue();
        expect(SetupLanguage::where('is_default', true)->first()->code)->toBe('en');
    });

    it('returns active languages', function (): void {
        $languages = SetupLanguage::getActive();

        expect($languages)->toHaveCount(1);
        expect($languages->first()->code)->toBe('en'); // Default first
    });

    it('returns active language codes', function (): void {
        $codes = SetupLanguage::getActiveCodes();

        expect($codes)->toContain('en');
    });

    it('returns default language code', function (): void {
        $code = SetupLanguage::getDefaultCode();

        expect($code)->toBe('en');
    });
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

describe('StaticConfigHelper Setup Collections', function (): void {
    it('returns setup collections for navigation', function (): void {
        $result = StaticConfigHelper::setupCollections();

        expect($result)->toBeArray();

        foreach ($result as $item) {
            expect($item)->toHaveKeys(['title', 'link', 'heroicon'])
                ->and($item['link'])->toStartWith('/setup-collections?key=')
                ->and($item['heroicon'])->toBe('archive-box');
        }
    });

    it('processes dynamic setup-collections navigation correctly', function (): void {
        $navigationStructure = [
            [
                'title' => 'Setup',
                'block_menus' => [
                    [
                        'title' => 'Data Management',
                        'dynamic' => 'setup-collections',
                    ],
                ],
            ],
        ];

        $reflection = new ReflectionClass(StaticConfigHelper::class);
        $method = $reflection->getMethod('processDynamicNavigation');
        $method->setAccessible(true);

        $result = $method->invoke(null, $navigationStructure);

        expect($result[0]['block_menus'][0])->toHaveKey('navigations')
            ->and($result[0]['block_menus'][0])->not->toHaveKey('dynamic')
            ->and($result[0]['block_menus'][0]['navigations'])->toBeArray();
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
        $response = $this->get('/setup-collections?key=example');

        $response->assertStatus(200);
    });
});

describe('Setup Collections List Component', function (): void {
    it('shows collection entries list', function (): void {
        Livewire::test('setup-collections-list', ['collectionKey' => 'example'])
            ->assertStatus(200)
            ->assertSee('Beispiele');
    });

    it('can open detail modal', function (): void {
        Livewire::test('setup-collections-list', ['collectionKey' => 'example'])
            ->call('listAction')
            ->assertDispatched('noerdModal');
    });
});

describe('Setup Collection Detail Component', function (): void {
    it('loads collection layout', function (): void {
        Livewire::test('setup-collection-detail', ['collectionKey' => 'example'])
            ->assertStatus(200)
            ->assertSet('collectionKey', 'example')
            ->assertSet('collectionLayout', fn($layout) => $layout !== null);
    });

    it('can save a new entry', function (): void {
        $component = Livewire::test('setup-collection-detail', ['collectionKey' => 'example'])
            ->set('model.title.de', 'Test Titel')
            ->set('model.title.en', 'Test Title')
            ->set('model.is_active', true)
            ->call('store');

        $component->assertSet('showSuccessIndicator', true);

        expect(SetupCollectionEntry::where('tenant_id', $this->tenant->id)->count())->toBe(1);
    });
});

describe('SetupLanguage Boot Events', function (): void {
    it('ensures only one default language exists', function (): void {
        // English is default from ensureDefaultLanguages
        $english = SetupLanguage::where('code', 'en')->first();
        expect($english->is_default)->toBeTrue();

        // Add German and set it as default
        $german = SetupLanguage::create([
            'code' => 'de',
            'name' => 'Deutsch',
            'is_active' => true,
            'is_default' => true,
            'sort_order' => 1,
        ]);

        // Refresh English from DB
        $english->refresh();

        expect($german->is_default)->toBeTrue();
        expect($english->is_default)->toBeFalse();
        expect(SetupLanguage::where('is_default', true)->count())->toBe(1);
    });

    it('sets new default after deleting default language', function (): void {
        // Add German as a non-default language
        SetupLanguage::create([
            'code' => 'de',
            'name' => 'Deutsch',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 1,
        ]);

        $english = SetupLanguage::where('code', 'en')->first();
        expect($english->is_default)->toBeTrue();

        $english->delete();

        // German should now be default
        $german = SetupLanguage::where('code', 'de')->first();
        expect($german->is_default)->toBeTrue();
    });

    it('can create a new language', function (): void {
        $french = SetupLanguage::create([
            'code' => 'fr',
            'name' => 'Français',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 2,
        ]);

        expect($french->exists)->toBeTrue();
        expect(SetupLanguage::count())->toBe(2);
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

    it('extracts german value from translatable array', function (): void {
        $reflection = new ReflectionClass(SetupFieldTypeConverter::class);
        $method = $reflection->getMethod('convertFromTranslatableField');
        $method->setAccessible(true);

        $result = $method->invoke(null, ['de' => 'Deutscher Text', 'en' => 'English Text']);

        expect($result)->toBe('Deutscher Text');
    });

    it('returns unchanged data for non-existent collection', function (): void {
        $data = ['title' => 'Test', 'description' => 'Description'];
        $result = SetupFieldTypeConverter::convertCollectionData($data, 'non-existent-collection');

        expect($result)->toBe($data);
    });
});

describe('Setup Languages List Component', function (): void {
    it('shows languages list', function (): void {
        Livewire::test('setup-languages-list')
            ->assertStatus(200)
            ->assertSee('English');
    });

    it('can open detail modal', function (): void {
        Livewire::test('setup-languages-list')
            ->call('listAction')
            ->assertDispatched('noerdModal');
    });
});

describe('Setup Language Detail Component', function (): void {
    it('loads for new language', function (): void {
        Livewire::test('setup-language-detail')
            ->assertStatus(200)
            ->assertSet('detailData.is_active', true);
    });

    it('loads existing language', function (): void {
        $english = SetupLanguage::where('code', 'en')->first();

        Livewire::withUrlParams(['setupLanguageId' => $english->id])->test('setup-language-detail')
            ->assertStatus(200)
            ->assertSet('detailData.code', 'en')
            ->assertSet('detailData.name', 'English');
    });

    it('can save a new language', function (): void {
        Livewire::test('setup-language-detail')
            ->set('detailData.code', 'fr')
            ->set('detailData.name', 'Français')
            ->set('detailData.is_active', true)
            ->set('detailData.is_default', false)
            ->call('store')
            ->assertSet('showSuccessIndicator', true);

        expect(SetupLanguage::where('code', 'fr')->exists())->toBeTrue();
    });
});
