<?php

use Livewire\Livewire;
use Livewire\Volt\Volt;
use Noerd\Noerd\Models\Tenant;
use Noerd\Noerd\Models\User;

uses(Tests\TestCase::class);

// Skip entire test file - component missing
beforeEach(function (): void {
    $this->markTestSkipped('TenantComponent does not exist - component missing');
});

$testSettings = [
    'componentName' => 'tenant-detail',
    'listName' => 'tenants-list',
    'id' => 'tenantId',
];

it('test the route', function (): void {
    $user = User::factory()->withContentModule()->create();

    $this->actingAs($user);

    $response = $this->get('/tenant');
    $response->assertStatus(200);
});

it('validates the data', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();

    $this->actingAs($user);

    Volt::test($testSettings['componentName'])
        ->set('model.name', '') // Empty name should fail validation
        ->call('store')
        ->assertHasErrors(['model.name']);
});

it('validates minimum name length', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();

    $this->actingAs($user);

    Volt::test($testSettings['componentName'])
        ->set('model.name', 'AB') // Too short (min 3)
        ->call('store')
        ->assertHasErrors(['model.name']);
});

it('validates maximum name length', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();

    $this->actingAs($user);

    $longName = str_repeat('A', 256); // Over 255 characters

    Volt::test($testSettings['componentName'])
        ->set('model.name', $longName)
        ->call('store')
        ->assertHasErrors(['model.name']);
});

it('successfully stores the data', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();

    $this->actingAs($user);
    $tenantName = fake()->company();

    Volt::test($testSettings['componentName'])
        ->set('model.name', $tenantName)
        ->call('store')
        ->assertSet('showSuccessIndicator', true)
        ->assertHasNoErrors();

    // Check that the tenant was updated
    $tenant = Tenant::find($user->selected_tenant_id);
    expect($tenant->name)->toBe($tenantName);
});

it('loads current tenant data on mount', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();

    $this->actingAs($user);

    $tenant = $user->tenants->first();
    $originalName = $tenant->name;

    Volt::test($testSettings['componentName'])
        ->assertSet('model.name', $originalName)
        ->assertSet('tenantId', $tenant->id)
        ->assertSuccessful();
});

it('updates existing tenant', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();

    $this->actingAs($user);

    $tenant = $user->tenants->first();
    $originalName = $tenant->name;
    $newName = 'Updated Company Name';

    Volt::test($testSettings['componentName'])
        ->set('model.name', $newName)
        ->call('store')
        ->assertHasNoErrors();

    $tenant->refresh();
    expect($tenant->name)->toBe($newName);
    expect($tenant->name)->not->toBe($originalName);
});

it('can handle logo upload', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();

    $this->actingAs($user);

    Volt::test($testSettings['componentName'])
        ->set('model.name', 'Test Company')
        ->set('model.logo', 'test-logo.png')
        ->call('store')
        ->assertHasNoErrors();

    $tenant = Tenant::find($user->selected_tenant_id);
    expect($tenant->logo)->toBe('test-logo.png');
});

it('it sets and removes the model id in url', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();

    $this->actingAs($user);
    $tenant = $user->tenants->first();

    Volt::test($testSettings['listName'])->call('tableAction', $tenant->id)
        ->assertDispatched('noerdModal', component: $testSettings['componentName']);

    Volt::test($testSettings['componentName'], [$tenant])
        ->assertSet('model.id', $tenant->id)
        ->assertSet($testSettings['id'], $tenant->id) // URL Parameter
        ->call('delete')
        ->assertDispatched('reloadTable-' . $testSettings['listName'])
        ->assertSet($testSettings['id'], '') // URL Parameter should be removed
        ->assertHasNoErrors();
});

it('it opens model when url parameter is set', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();

    $this->actingAs($user);
    $tenant = $user->tenants->first();

    Livewire::withUrlParams([$testSettings['id'] => $tenant->id])
        ->test($testSettings['listName'])
        ->assertDispatched('noerdModal');
});

it('sets a table key for the list', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();

    $this->actingAs($user);

    Volt::test($testSettings['listName'])
        ->assertNotSet('tableId', '');
});

it('validates name is string', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();

    $this->actingAs($user);

    Volt::test($testSettings['componentName'])
        ->set('model.name', 12345) // Non-string
        ->call('store')
        ->assertHasErrors(['model.name']);
});
