<?php

declare(strict_types=1);

/*
 | End-to-end coverage of `collections.mode = database`: the definition rows in
 | setup_collection_definitions must drive the same screens the YAML files drive
 | in the default mode — sidebar, collection list, entry form, select options —
 | and must never cross the tenant boundary.
 */

use Livewire\Livewire;
use Noerd\Contracts\SetupCollectionDefinitionRepositoryContract;
use Noerd\Helpers\SetupCollectionHelper;
use Noerd\Models\SetupCollection;
use Noerd\Models\SetupCollectionDefinition;
use Noerd\Models\SetupCollectionEntry;
use Noerd\Models\Tenant;
use Noerd\Navigation\SetupCollectionsNavigationProvider;
use Noerd\Repositories\DatabaseSetupCollectionDefinitionRepository;
use Noerd\Tests\TestCase;
use Noerd\Tests\Traits\CreatesSetupUser;

uses(TestCase::class);
uses(CreatesSetupUser::class);

beforeEach(function (): void {
    config(['noerd.collections.mode' => 'database']);
    config(['noerd.collections.show_definitions_ui' => true]);
    DatabaseSetupCollectionDefinitionRepository::resetCache();
    app()->forgetInstance(SetupCollectionDefinitionRepositoryContract::class);
    app()->forgetInstance(SetupCollectionHelper::class);
});

function zzDefinition(int $tenantId, string $filename = 'zz_things'): SetupCollectionDefinition
{
    return SetupCollectionDefinition::create([
        'tenant_id' => $tenantId,
        'filename' => $filename,
        'key' => mb_strtoupper($filename),
        'title' => 'Zz Thing',
        'title_list' => 'Zz Things',
        'description' => '',
        'fields' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'colspan' => 6],
            ['name' => 'code', 'label' => 'Code', 'type' => 'text', 'colspan' => 6],
        ],
    ]);
}

it('never leaks another tenant definition', function (): void {
    ['user' => $user, 'tenant' => $tenantA] = $this->createUserWithSetupAccess();
    $foreign = Tenant::factory()->create();
    zzDefinition($foreign->id, 'zz_foreign');
    zzDefinition($tenantA->id);

    $this->actingAs($user);
    $repo = app(SetupCollectionDefinitionRepositoryContract::class);

    $visible = $repo->all()->pluck('filename')->all();

    expect($visible)->toContain('zz_things')
        ->and($visible)->not->toContain('zz_foreign')
        ->and($repo->find('zz_foreign'))->toBeNull()
        ->and($repo->resolveFields('zz_foreign'))->toBeNull();
});

it('renders the collections list from a database definition', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithSetupAccess();
    zzDefinition($tenant->id);
    $collection = SetupCollection::create(['tenant_id' => $tenant->id, 'collection_key' => 'ZZ_THINGS', 'name' => 'Zz Things']);
    SetupCollectionEntry::create([
        'tenant_id' => $tenant->id,
        'setup_collection_id' => $collection->id,
        'data' => ['name' => 'Hammer', 'code' => 'H1'],
    ]);

    $this->actingAs($user);

    Livewire::test('noerd::setup-collections-list', ['collectionKey' => 'zz_things'])
        ->assertOk()
        ->assertSee('Hammer');
});

it('saves a new entry through the detail component', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithSetupAccess();
    zzDefinition($tenant->id);
    SetupCollection::create(['tenant_id' => $tenant->id, 'collection_key' => 'ZZ_THINGS', 'name' => 'Zz Things']);

    $this->actingAs($user);

    Livewire::test('noerd::setup-collection-detail', ['collectionKey' => 'zz_things'])
        ->set('detailData.name', 'Saw')
        ->set('detailData.code', 'S1')
        ->call('store')
        ->assertHasNoErrors();

    $saved = SetupCollectionEntry::whereHas('collection', fn($q) => $q->where('collection_key', 'ZZ_THINGS'))->get();

    expect($saved)->toHaveCount(1)
        ->and($saved->first()->data['name'])->toBe('Saw')
        ->and($saved->first()->data['code'])->toBe('S1')
        ->and($saved->first()->tenant_id)->toBe($tenant->id);
});

it('lists database definitions in the dynamic setup navigation', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithSetupAccess();
    zzDefinition($tenant->id);

    $this->actingAs($user);

    $items = app(SetupCollectionsNavigationProvider::class)->items();

    expect(collect($items)->pluck('title')->all())->toContain('Zz Things');
});

it('serves the collections list route in database mode', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithSetupAccess();
    zzDefinition($tenant->id);

    $this->actingAs($user);

    $this->get(route('noerd.setup-collections', ['key' => 'zz_things']))->assertSuccessful();
});

it('renders the definitions list and detail', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithSetupAccess();
    zzDefinition($tenant->id);

    $this->actingAs($user);

    Livewire::test('noerd::setup-collection-definitions-list')->assertOk()->assertSee('Zz Things');
    Livewire::test('noerd::setup-collection-definition-detail', ['modelId' => 'zz_things'])
        ->assertOk()
        ->assertSet('detailData.titleList', 'Zz Things');
});

it('never hydrates a foreign tenant definition into the detail', function (): void {
    ['user' => $user] = $this->createUserWithSetupAccess();
    $foreign = Tenant::factory()->create();
    zzDefinition($foreign->id, 'zz_foreign');

    $this->actingAs($user);

    Livewire::test('noerd::setup-collection-definition-detail', ['modelId' => 'zz_foreign'])
        ->assertSet('detailData.titleList', '');
});

it('seeds a newly created tenant from the shipped YAML definitions', function (): void {
    $tenant = Tenant::factory()->create();

    $definitions = SetupCollectionDefinition::where('tenant_id', $tenant->id)->get();

    // The countries collection ships with the package and is what every tenant
    // starts with — its entries are seeded alongside, so without the definition
    // the tenant would hold data it cannot render.
    expect($definitions->pluck('filename')->all())->toContain('countries')
        ->and(SetupCollection::where('tenant_id', $tenant->id)->where('collection_key', 'COUNTRIES')->exists())->toBeTrue();
});

it('leaves a newly created tenant untouched in yaml mode', function (): void {
    config(['noerd.collections.mode' => 'yaml']);

    $tenant = Tenant::factory()->create();

    expect(SetupCollectionDefinition::where('tenant_id', $tenant->id)->count())->toBe(0);
});

it('derives the definitions UI flag from the mode instead of a second config key', function (): void {
    // A published config edited to database mode without the env var used to
    // open the routes while the navigation entry stayed hidden.
    expect(config('noerd.collections.show_definitions_ui'))->toBeTrue();

    config(['noerd.collections.mode' => 'yaml']);
    (new \Noerd\Providers\NoerdServiceProvider(app()))->register();

    expect(config('noerd.collections.show_definitions_ui'))->toBeFalse();
});

describe('definitions route', function (): void {
    it('returns 404 for /setup-collection-definitions in yaml mode', function (): void {
        config(['noerd.collections.mode' => 'yaml']);
        config(['noerd.collections.show_definitions_ui' => false]);

        ['user' => $user] = $this->createUserWithSetupAccess();
        $this->actingAs($user);

        $response = $this->get('/setup/collection-definitions');
        $response->assertNotFound();
    });
});
