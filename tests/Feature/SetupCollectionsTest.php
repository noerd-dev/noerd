<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Noerd\Noerd\Helpers\SetupCollectionHelper;
use Noerd\Noerd\Helpers\StaticConfigHelper;
use Noerd\Noerd\Models\Profile;
use Noerd\Noerd\Models\SetupCollection;
use Noerd\Noerd\Models\SetupCollectionEntry;
use Noerd\Noerd\Models\SetupLanguage;
use Noerd\Noerd\Models\Tenant;
use Noerd\Noerd\Models\User;
use Noerd\Noerd\Services\SetupFieldTypeConverter;

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

    $this->user = User::factory()->create([
        'selected_tenant_id' => $this->tenant->id,
        'selected_app' => 'setup',
    ]);

    // Attach user to tenant with admin profile
    $this->user->tenants()->attach($this->tenant->id, ['profile_id' => $adminProfile->id]);

    $this->actingAs($this->user);
});

describe('SetupLanguage Model', function (): void {
    it('creates default languages when none exist', function (): void {
        SetupLanguage::query()->delete();
        SetupLanguage::ensureDefaultLanguages();

        expect(SetupLanguage::count())->toBe(2);
        expect(SetupLanguage::where('code', 'de')->exists())->toBeTrue();
        expect(SetupLanguage::where('code', 'en')->exists())->toBeTrue();
        expect(SetupLanguage::where('is_default', true)->first()->code)->toBe('de');
    });

    it('returns active languages', function (): void {
        $languages = SetupLanguage::getActive();

        expect($languages)->toHaveCount(2);
        expect($languages->first()->code)->toBe('de'); // Default first
    });

    it('returns active language codes', function (): void {
        $codes = SetupLanguage::getActiveCodes();

        expect($codes)->toContain('de', 'en');
    });

    it('returns default language code', function (): void {
        $code = SetupLanguage::getDefaultCode();

        expect($code)->toBe('de');
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
        Volt::test('setup-collections-list', ['collectionKey' => 'example'])
            ->assertStatus(200)
            ->assertSee('Beispiele');
    });

    it('can open detail modal', function (): void {
        Volt::test('setup-collections-list', ['collectionKey' => 'example'])
            ->call('tableAction')
            ->assertDispatched('noerdModal');
    });
});

describe('Setup Collection Detail Component', function (): void {
    it('loads collection layout', function (): void {
        Volt::test('setup-collection-detail', ['collectionKey' => 'example'])
            ->assertStatus(200)
            ->assertSet('collectionKey', 'example')
            ->assertSet('collectionLayout', fn ($layout) => $layout !== null);
    });

    it('can save a new entry', function (): void {
        $component = Volt::test('setup-collection-detail', ['collectionKey' => 'example'])
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
        // German is default from ensureDefaultLanguages
        $german = SetupLanguage::where('code', 'de')->first();
        expect($german->is_default)->toBeTrue();

        // Set English as default
        $english = SetupLanguage::where('code', 'en')->first();
        $english->is_default = true;
        $english->save();

        // Refresh German from DB
        $german->refresh();

        expect($english->is_default)->toBeTrue();
        expect($german->is_default)->toBeFalse();
        expect(SetupLanguage::where('is_default', true)->count())->toBe(1);
    });

    it('sets new default after deleting default language', function (): void {
        $german = SetupLanguage::where('code', 'de')->first();
        expect($german->is_default)->toBeTrue();

        $german->delete();

        // English should now be default
        $english = SetupLanguage::where('code', 'en')->first();
        expect($english->is_default)->toBeTrue();
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
        expect(SetupLanguage::count())->toBe(3);
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
        Volt::test('setup-languages-list')
            ->assertStatus(200)
            ->assertSee('Deutsch')
            ->assertSee('English');
    });

    it('can open detail modal', function (): void {
        Volt::test('setup-languages-list')
            ->call('tableAction')
            ->assertDispatched('noerdModal');
    });
});

describe('Setup Language Detail Component', function (): void {
    it('loads for new language', function (): void {
        Volt::test('setup-language-detail')
            ->assertStatus(200)
            ->assertSet('language.is_active', true);
    });

    it('loads existing language', function (): void {
        $german = SetupLanguage::where('code', 'de')->first();

        Volt::test('setup-language-detail', ['modelId' => $german->id])
            ->assertStatus(200)
            ->assertSet('language.code', 'de')
            ->assertSet('language.name', 'Deutsch');
    });

    it('can save a new language', function (): void {
        Volt::test('setup-language-detail')
            ->set('language.code', 'fr')
            ->set('language.name', 'Français')
            ->set('language.is_active', true)
            ->set('language.is_default', false)
            ->call('store')
            ->assertSet('showSuccessIndicator', true);

        expect(SetupLanguage::where('code', 'fr')->exists())->toBeTrue();
    });
});
