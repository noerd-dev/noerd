<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\SetupCollection;
use Noerd\Models\SetupCollectionEntry;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 | The collection entry search interpolates each field name into a
 | JSON_EXTRACT() path inside orWhereRaw(). $collectionLayout supplies those
 | names, so it must never carry client input: an injected name closed the
 | JSON path and appended arbitrary SQL. Two independent defenses are asserted,
 | because #[Locked] rejects client UPDATES but does not protect MOUNT
 | arguments — which the modal stack and the generic component page take from
 | the client.
 */

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());
    $tenantId = TenantHelper::getSelectedTenantId();

    $collection = SetupCollection::create([
        'tenant_id' => $tenantId,
        'collection_key' => 'ZZINJECT',
        'name' => 'Injection Fixture',
    ]);

    SetupCollectionEntry::create([
        'tenant_id' => $tenantId,
        'setup_collection_id' => $collection->id,
        'data' => ['name' => 'Alpha Entry'],
        'sort' => 1,
    ]);
});

it('rejects a client update to the collection layout', function (): void {
    expect(fn() => Livewire::test('noerd::setup-collections-list', ['collectionKey' => 'zzinject'])
        ->set('collectionLayout', ['fields' => [['name' => 'detailData.name', 'type' => 'text']]]))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('ignores a field name that is not a plain identifier instead of interpolating it', function (): void {
    $component = Livewire::test('noerd::setup-collections-list', ['collectionKey' => 'zzinject']);
    $instance = $component->instance();

    // Defense in depth, exercised directly on the query builder: mount() always
    // derives the layout server-side, so this shape is not reachable through the
    // component today — the assertion pins the escaping rule itself, so a future
    // caller that does feed the layout cannot reintroduce the injection. The
    // payload closes the JSON path and ORs a tautology.
    $instance->collectionLayout = [
        'fields' => [['name' => 'x") OR 1=1 OR JSON_EXTRACT(data,"$.y', 'type' => 'text']],
    ];
    $instance->search = 'nomatch-xyz';

    $rows = $instance->listData()['rows'];

    expect($rows->total())->toBe(0);
});

it('still searches on a legitimate field name', function (): void {
    $instance = Livewire::test('noerd::setup-collections-list', ['collectionKey' => 'zzinject'])->instance();
    $instance->collectionLayout = [
        'fields' => [['name' => 'detailData.name', 'type' => 'text']],
    ];

    $instance->search = 'Alpha';
    expect($instance->listData()['rows']->total())->toBe(1);

    $instance->search = 'nomatch-xyz';
    expect($instance->listData()['rows']->total())->toBe(0);
});
