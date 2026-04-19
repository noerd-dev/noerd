<?php

use Livewire\Livewire;
use Noerd\Contracts\SetupCollectionDefinitionRepositoryContract;
use Noerd\Helpers\SetupCollectionHelper;
use Noerd\Models\SetupCollection;
use Noerd\Models\SetupCollectionDefinition;
use Noerd\Models\SetupCollectionEntry;
use Noerd\Repositories\DatabaseSetupCollectionDefinitionRepository;
use Noerd\Tests\Traits\CreatesSetupUser;

uses(Tests\TestCase::class);
uses(CreatesSetupUser::class);

beforeEach(function (): void {
    config(['noerd.collections.mode' => 'database']);
    config(['noerd.collections.show_definitions_ui' => true]);
    DatabaseSetupCollectionDefinitionRepository::resetCache();
    app()->forgetInstance(SetupCollectionDefinitionRepositoryContract::class);
    app()->forgetInstance(SetupCollectionHelper::class);
});

/**
 * Create an "expense_categories" collection definition in the database for the given tenant.
 */
function createExpenseCategoriesDefinition(int $tenantId): SetupCollectionDefinition
{
    return SetupCollectionDefinition::create([
        'tenant_id' => $tenantId,
        'filename' => 'expense_categories',
        'key' => 'EXPENSE_CATEGORIES',
        'title' => 'Ausgabenkategorie',
        'title_list' => 'Ausgabenkategorien',
        'description' => '',
        'fields' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'translatableText', 'colspan' => 6],
        ],
    ]);
}

it('renders the list component and shows existing definitions', function (): void {
    ['user' => $user] = $this->createUserWithSetupAccess();
    $this->actingAs($user);

    $response = $this->get('/setup-collection-definitions');
    $response->assertOk();

    Livewire::test('noerd::setup-collection-definitions-list')
        ->assertNotSet('listId', '');
});

it('dispatches modal when listAction is called', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithSetupAccess();
    $this->actingAs($user);

    createExpenseCategoriesDefinition($tenant->id);

    Livewire::test('noerd::setup-collection-definitions-list')
        ->call('listAction', 'expense_categories')
        ->assertDispatched('noerdModal', modalComponent: 'noerd::setup-collection-definition-detail');
});

it('loads existing collection definition in detail component', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithSetupAccess();
    $this->actingAs($user);

    createExpenseCategoriesDefinition($tenant->id);

    Livewire::test('noerd::setup-collection-definition-detail', ['modelId' => 'expense_categories'])
        ->assertSet('isEditing', true)
        ->assertSet('detailData.filename', 'expense_categories')
        ->assertSet('detailData.title', 'Ausgabenkategorie');
});

it('loads pageLayout with metadata fields from YAML config', function (): void {
    ['user' => $user] = $this->createUserWithSetupAccess();
    $this->actingAs($user);

    $component = Livewire::test('noerd::setup-collection-definition-detail');

    $pageLayout = $component->get('pageLayout');
    expect($pageLayout)->not->toBeEmpty();
    expect($pageLayout['fields'])->toBeArray();

    $fieldNames = array_column($pageLayout['fields'], 'name');
    expect($fieldNames)->toContain('detailData.filename');
    expect($fieldNames)->toContain('detailData.title');
    expect($fieldNames)->toContain('detailData.titleList');
});

it('allows renaming the filename of an existing collection definition', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithSetupAccess();
    $this->actingAs($user);

    createExpenseCategoriesDefinition($tenant->id);

    Livewire::test('noerd::setup-collection-definition-detail', ['modelId' => 'expense_categories'])
        ->set('detailData.filename', 'expense_categories_renamed')
        ->call('store')
        ->assertHasNoErrors();

    expect(SetupCollectionDefinition::where('tenant_id', $tenant->id)->where('filename', 'expense_categories_renamed')->exists())->toBeTrue();
    expect(SetupCollectionDefinition::where('tenant_id', $tenant->id)->where('filename', 'expense_categories')->exists())->toBeFalse();
    expect(SetupCollectionDefinition::where('tenant_id', $tenant->id)->where('filename', 'expense_categories_renamed')->first()->key)->toBe('EXPENSE_CATEGORIES_RENAMED');
});

it('prevents renaming to an existing filename', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithSetupAccess();
    $this->actingAs($user);

    createExpenseCategoriesDefinition($tenant->id);

    SetupCollectionDefinition::create([
        'tenant_id' => $tenant->id,
        'filename' => 'expense_categories_renamed',
        'key' => 'EXPENSE_CATEGORIES_RENAMED',
        'title' => 'Existing',
        'title_list' => 'Existing',
        'fields' => [],
    ]);

    Livewire::test('noerd::setup-collection-definition-detail', ['modelId' => 'expense_categories'])
        ->set('detailData.filename', 'expense_categories_renamed')
        ->call('store')
        ->assertHasErrors('detailData.filename');

    expect(SetupCollectionDefinition::where('tenant_id', $tenant->id)->where('filename', 'expense_categories')->exists())->toBeTrue();
});

it('creates a new collection definition with correct structure', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithSetupAccess();
    $this->actingAs($user);

    Livewire::test('noerd::setup-collection-definition-detail')
        ->set('detailData.filename', 'test_store')
        ->set('detailData.title', 'Test Store')
        ->set('detailData.titleList', 'Test Stores')
        ->call('store')
        ->assertHasNoErrors();

    $definition = SetupCollectionDefinition::where('tenant_id', $tenant->id)->where('filename', 'test_store')->first();
    expect($definition)->not->toBeNull();
    expect($definition->title)->toBe('Test Store');
    expect($definition->title_list)->toBe('Test Stores');
    expect($definition->key)->toBe('TEST_STORE');
});

it('ensures a SetupCollection instance bucket exists after creating a definition', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithSetupAccess();
    $this->actingAs($user);

    Livewire::test('noerd::setup-collection-definition-detail')
        ->set('detailData.filename', 'new_things')
        ->set('detailData.title', 'Thing')
        ->set('detailData.titleList', 'Things')
        ->call('store')
        ->assertHasNoErrors();

    $bucket = SetupCollection::where('tenant_id', $tenant->id)
        ->where('collection_key', 'NEW_THINGS')
        ->first();

    expect($bucket)->not->toBeNull();
    expect($bucket->name)->toBe('Things');
});

it('prevents duplicate filenames', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithSetupAccess();
    $this->actingAs($user);

    SetupCollectionDefinition::create([
        'tenant_id' => $tenant->id,
        'filename' => 'test_duplicate',
        'key' => 'TEST_DUPLICATE',
        'title' => 'Existing',
        'title_list' => 'Existing',
        'fields' => [],
    ]);

    Livewire::test('noerd::setup-collection-definition-detail')
        ->set('detailData.filename', 'test_duplicate')
        ->set('detailData.title', 'Duplicate')
        ->set('detailData.titleList', 'Duplicates')
        ->call('store')
        ->assertHasErrors('detailData.filename');
});

it('validates required fields', function (): void {
    ['user' => $user] = $this->createUserWithSetupAccess();
    $this->actingAs($user);

    Livewire::test('noerd::setup-collection-definition-detail')
        ->set('detailData.filename', '')
        ->set('detailData.title', '')
        ->set('detailData.titleList', '')
        ->call('store')
        ->assertHasErrors([
            'detailData.filename',
            'detailData.title',
            'detailData.titleList',
        ]);
});

it('validates filename format (no dashes allowed)', function (): void {
    ['user' => $user] = $this->createUserWithSetupAccess();
    $this->actingAs($user);

    Livewire::test('noerd::setup-collection-definition-detail')
        ->set('detailData.filename', 'Invalid Name!')
        ->set('detailData.title', 'Test')
        ->set('detailData.titleList', 'Tests')
        ->call('store')
        ->assertHasErrors('detailData.filename');
});

it('normalizes filename by lowercasing and stripping yml extension', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithSetupAccess();
    $this->actingAs($user);

    Livewire::test('noerd::setup-collection-definition-detail')
        ->set('detailData.filename', 'FILM.YML')
        ->set('detailData.title', 'Film')
        ->set('detailData.titleList', 'Films')
        ->call('store')
        ->assertHasNoErrors();

    expect(SetupCollectionDefinition::where('tenant_id', $tenant->id)->where('filename', 'film')->exists())->toBeTrue();
});

it('normalizes hyphens to underscores in filename', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithSetupAccess();
    $this->actingAs($user);

    Livewire::test('noerd::setup-collection-definition-detail')
        ->set('detailData.filename', 'my-collection')
        ->set('detailData.title', 'My Collection')
        ->set('detailData.titleList', 'My Collections')
        ->call('store')
        ->assertHasNoErrors();

    expect(SetupCollectionDefinition::where('tenant_id', $tenant->id)->where('filename', 'my_collection')->exists())->toBeTrue();
});

it('adds and removes fields', function (): void {
    ['user' => $user] = $this->createUserWithSetupAccess();
    $this->actingAs($user);

    Livewire::test('noerd::setup-collection-definition-detail')
        ->assertSet('fields', [])
        ->call('addField')
        ->assertCount('fields', 1)
        ->call('addField')
        ->assertCount('fields', 2)
        ->call('removeField', 0)
        ->assertCount('fields', 1);
});

it('stores fields in the collection definition', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithSetupAccess();
    $this->actingAs($user);

    Livewire::test('noerd::setup-collection-definition-detail')
        ->set('detailData.filename', 'test_definition')
        ->set('detailData.title', 'Test Def')
        ->set('detailData.titleList', 'Test Defs')
        ->call('addField')
        ->set('fields.0.name', 'my_field')
        ->set('fields.0.label', 'My Field')
        ->set('fields.0.type', 'translatableText')
        ->set('fields.0.colspan', 6)
        ->call('store')
        ->assertHasNoErrors();

    $definition = SetupCollectionDefinition::where('tenant_id', $tenant->id)->where('filename', 'test_definition')->first();
    expect($definition->fields)->toHaveCount(1);
    expect($definition->fields[0]['name'])->toBe('my_field');
    expect($definition->fields[0]['type'])->toBe('translatableText');
});

it('copies a collection definition with key, title and titleList all suffixed with 2', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithSetupAccess();
    $this->actingAs($user);

    createExpenseCategoriesDefinition($tenant->id);

    Livewire::test('noerd::setup-collection-definition-detail', ['modelId' => 'expense_categories'])
        ->call('copy')
        ->assertHasNoErrors();

    $copy = SetupCollectionDefinition::where('tenant_id', $tenant->id)->where('filename', 'expense_categories2')->first();
    expect($copy)->not->toBeNull();
    expect($copy->key)->toBe('EXPENSE_CATEGORIES2');
    expect($copy->title)->toBe('Ausgabenkategorie2');
    expect($copy->title_list)->toBe('Ausgabenkategorien2');
    expect($copy->fields)->toHaveCount(1);

    // Mirrors into the setup_collections instance table.
    $instance = SetupCollection::where('tenant_id', $tenant->id)->where('collection_key', 'EXPENSE_CATEGORIES2')->first();
    expect($instance)->not->toBeNull();
    expect($instance->name)->toBe('Ausgabenkategorien2');
});

it('prevents copying when target definition already exists', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithSetupAccess();
    $this->actingAs($user);

    createExpenseCategoriesDefinition($tenant->id);

    SetupCollectionDefinition::create([
        'tenant_id' => $tenant->id,
        'filename' => 'expense_categories2',
        'key' => 'EXPENSE_CATEGORIES2',
        'title' => 'Existing',
        'title_list' => 'Existing',
        'fields' => [],
    ]);

    Livewire::test('noerd::setup-collection-definition-detail', ['modelId' => 'expense_categories'])
        ->call('copy')
        ->assertHasErrors('detailData.filename');
});

it('deletes a collection definition', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithSetupAccess();
    $this->actingAs($user);

    $definition = SetupCollectionDefinition::create([
        'tenant_id' => $tenant->id,
        'filename' => 'test_definition_2',
        'key' => 'TEST_DEFINITION_2',
        'title' => 'To Delete',
        'title_list' => 'To Delete',
        'fields' => [],
    ]);

    Livewire::test('noerd::setup-collection-definition-detail', ['modelId' => 'test_definition_2'])
        ->call('delete');

    expect(SetupCollectionDefinition::find($definition->id))->toBeNull();
});

it('deletes associated SetupCollection and entries when deleting a definition', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithSetupAccess();
    $this->actingAs($user);

    SetupCollectionDefinition::create([
        'tenant_id' => $tenant->id,
        'filename' => 'test_definition_2',
        'key' => 'TEST_DEFINITION_2',
        'title' => 'To Delete',
        'title_list' => 'To Delete',
        'fields' => [],
    ]);

    $collection = SetupCollection::create([
        'tenant_id' => $tenant->id,
        'collection_key' => 'TEST_DEFINITION_2',
        'name' => 'To Delete',
    ]);

    $entry = SetupCollectionEntry::create([
        'tenant_id' => $tenant->id,
        'setup_collection_id' => $collection->id,
        'data' => ['name' => 'x'],
        'sort' => 0,
    ]);

    Livewire::test('noerd::setup-collection-definition-detail', ['modelId' => 'test_definition_2'])
        ->call('delete');

    expect(SetupCollectionDefinition::where('tenant_id', $tenant->id)->where('filename', 'test_definition_2')->exists())->toBeFalse();
    expect(SetupCollection::find($collection->id))->toBeNull();
    expect(SetupCollectionEntry::find($entry->id))->toBeNull();
});

it('shows rename confirmation when a field name is changed', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithSetupAccess();
    $this->actingAs($user);

    SetupCollectionDefinition::create([
        'tenant_id' => $tenant->id,
        'filename' => 'rename_test',
        'key' => 'RENAME_TEST',
        'title' => 'Rename Test',
        'title_list' => 'Rename Tests',
        'fields' => [
            ['name' => 'headline1', 'label' => 'Headline', 'type' => 'text', 'colspan' => 6],
        ],
    ]);

    Livewire::test('noerd::setup-collection-definition-detail', ['modelId' => 'rename_test'])
        ->set('fields.0.name', 'headline_one')
        ->call('store')
        ->assertSet('showRenameConfirmation', true)
        ->assertSet('pendingRenames', ['headline1' => 'headline_one']);
});

it('renames field keys in entry data when confirmed', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithSetupAccess();
    $this->actingAs($user);

    SetupCollectionDefinition::create([
        'tenant_id' => $tenant->id,
        'filename' => 'rename_test',
        'key' => 'RENAME_TEST',
        'title' => 'Rename Test',
        'title_list' => 'Rename Tests',
        'fields' => [
            ['name' => 'headline1', 'label' => 'Headline', 'type' => 'text', 'colspan' => 6],
        ],
    ]);

    $collection = SetupCollection::create([
        'tenant_id' => $tenant->id,
        'collection_key' => 'RENAME_TEST',
        'name' => 'Rename Test',
    ]);

    $entry = SetupCollectionEntry::create([
        'tenant_id' => $tenant->id,
        'setup_collection_id' => $collection->id,
        'data' => ['headline1' => 'Hello World', 'other' => 'unchanged'],
        'sort' => 0,
    ]);

    Livewire::test('noerd::setup-collection-definition-detail', ['modelId' => 'rename_test'])
        ->set('fields.0.name', 'headline_one')
        ->call('store')
        ->assertSet('showRenameConfirmation', true)
        ->call('confirmRenameAndSave')
        ->assertSet('showRenameConfirmation', false);

    $entry->refresh();
    expect($entry->data)->toHaveKey('headline_one', 'Hello World');
    expect($entry->data)->not->toHaveKey('headline1');
    expect($entry->data)->toHaveKey('other', 'unchanged');
});

it('skips database rename when user declines', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithSetupAccess();
    $this->actingAs($user);

    SetupCollectionDefinition::create([
        'tenant_id' => $tenant->id,
        'filename' => 'rename_test',
        'key' => 'RENAME_TEST',
        'title' => 'Rename Test',
        'title_list' => 'Rename Tests',
        'fields' => [
            ['name' => 'headline1', 'label' => 'Headline', 'type' => 'text', 'colspan' => 6],
        ],
    ]);

    $collection = SetupCollection::create([
        'tenant_id' => $tenant->id,
        'collection_key' => 'RENAME_TEST',
        'name' => 'Rename Test',
    ]);

    $entry = SetupCollectionEntry::create([
        'tenant_id' => $tenant->id,
        'setup_collection_id' => $collection->id,
        'data' => ['headline1' => 'Hello World'],
        'sort' => 0,
    ]);

    Livewire::test('noerd::setup-collection-definition-detail', ['modelId' => 'rename_test'])
        ->set('fields.0.name', 'headline_one')
        ->call('store')
        ->assertSet('showRenameConfirmation', true)
        ->call('skipRenameAndSave')
        ->assertSet('showRenameConfirmation', false);

    $entry->refresh();
    expect($entry->data)->toHaveKey('headline1', 'Hello World');
    expect($entry->data)->not->toHaveKey('headline_one');
});
