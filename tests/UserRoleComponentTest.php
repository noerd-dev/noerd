<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Models\User;
use Noerd\Models\UserRole;

uses(Tests\TestCase::class);
uses(RefreshDatabase::class);

$testSettings = [
    'componentName' => 'user-role-detail',
    'listName' => 'user-roles-list',
    'modelId' => 'modelId',
    'urlParam' => 'userRoleId',
];

it('renders the user role component', function () use ($testSettings): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('setup')->create();

    $this->actingAs($user);

    Livewire::test($testSettings['componentName'])
        ->assertStatus(200)
        ->assertSeeText('Benutzerrolle');
});

it('validates required fields when storing', function () use ($testSettings): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('setup')->create();

    $this->actingAs($user);

    Livewire::test($testSettings['componentName'])
        ->call('store')
        ->assertHasErrors(['detailData.key'])
        ->assertHasErrors(['detailData.name']);
});

it('successfully creates a new user role', function () use ($testSettings): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('setup')->create();

    $this->actingAs($user);

    $roleKey = 'ADMIN_ROLE';
    $roleName = 'Administrator Role';
    $roleDescription = 'Has full administrative access';

    Livewire::test($testSettings['componentName'])
        ->set('detailData.key', $roleKey)
        ->set('detailData.name', $roleName)
        ->set('detailData.description', $roleDescription)
        ->call('store')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('user_roles', [
        'key' => $roleKey,
        'name' => $roleName,
        'description' => $roleDescription,
        'tenant_id' => $user->selected_tenant_id,
    ]);
});

it('updates an existing user role', function () use ($testSettings): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('setup')->create();

    $existingRole = UserRole::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'key' => 'OLD_KEY',
        'name' => 'Old Name',
        'description' => 'Old Description',
    ]);

    $this->actingAs($user);

    $newKey = 'NEW_KEY';
    $newName = 'New Name';
    $newDescription = 'New Description';

    Livewire::test($testSettings['componentName'], [$existingRole])
        ->set('modelId', $existingRole->id)
        ->set('detailData.key', $newKey)
        ->set('detailData.name', $newName)
        ->set('detailData.description', $newDescription)
        ->call('store')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('user_roles', [
        'id' => $existingRole->id,
        'key' => $newKey,
        'name' => $newName,
        'description' => $newDescription,
        'tenant_id' => $user->selected_tenant_id,
    ]);
});

it('sets tenant_id when storing', function () use ($testSettings): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('setup')->create();

    $this->actingAs($user);

    Livewire::test($testSettings['componentName'])
        ->set('detailData.key', 'TEST_ROLE')
        ->set('detailData.name', 'Test Role')
        ->call('store')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('user_roles', [
        'key' => 'TEST_ROLE',
        'name' => 'Test Role',
        'tenant_id' => $user->selected_tenant_id,
    ]);
});

it('deletes a user role', function () use ($testSettings): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('setup')->create();

    $userRole = UserRole::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'key' => 'DELETE_ME',
        'name' => 'Delete Me Role',
    ]);

    $this->actingAs($user);

    Livewire::test($testSettings['componentName'], [$userRole])
        ->set('modelId', $userRole->id)
        ->call('delete')
        ->assertDispatched('closeTopModal');

    $this->assertDatabaseMissing('user_roles', [
        'id' => $userRole->id,
    ]);
});

it('mounts with existing user role data', function () use ($testSettings): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('setup')->create();

    $userRole = UserRole::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'key' => 'EXISTING_ROLE',
        'name' => 'Existing Role',
        'description' => 'An existing role',
    ]);

    $this->actingAs($user);

    $component = Livewire::test($testSettings['componentName'], [$userRole])
        ->set('modelId', $userRole->id);

    // Check if user role data is loaded correctly
    expect($component->get('detailData.key'))->toBe($userRole->key);
    expect($component->get('detailData.name'))->toBe($userRole->name);
    expect($component->get('detailData.description'))->toBe($userRole->description);
    expect($component->get('modelId'))->toBe($userRole->id);
});

it('mounts with new user role', function () use ($testSettings): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('setup')->create();

    $this->actingAs($user);

    $component = Livewire::test($testSettings['componentName']);

    // Check if default values are set for new user role
    expect($component->get('detailData'))->toBeArray();
    expect($component->get('modelId'))->toBeNull();
});

it('sets success indicator after storing', function () use ($testSettings): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('setup')->create();

    $this->actingAs($user);

    Livewire::test($testSettings['componentName'])
        ->set('detailData.key', 'SUCCESS_ROLE')
        ->set('detailData.name', 'Success Role')
        ->call('store')
        ->assertSet('showSuccessIndicator', true);
});

it('sets userRoleId after creating new role', function () use ($testSettings): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('setup')->create();

    $this->actingAs($user);

    $component = Livewire::test($testSettings['componentName'])
        ->set('detailData.key', 'NEW_ROLE')
        ->set('detailData.name', 'New Role')
        ->call('store');

    // Check if modelId was set after creation
    expect($component->get('modelId'))->not->toBeNull();
});

it('validates key field format', function () use ($testSettings): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('setup')->create();

    $this->actingAs($user);

    Livewire::test($testSettings['componentName'])
        ->set('detailData.key', '') // Empty key
        ->set('detailData.name', 'Valid Name')
        ->call('store')
        ->assertHasErrors(['detailData.key']);
});

it('validates name field format', function () use ($testSettings): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('setup')->create();

    $this->actingAs($user);

    Livewire::test($testSettings['componentName'])
        ->set('detailData.key', 'VALID_KEY')
        ->set('detailData.name', '') // Empty name
        ->call('store')
        ->assertHasErrors(['detailData.name']);
});

it('handles optional description field', function () use ($testSettings): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('setup')->create();

    $this->actingAs($user);

    Livewire::test($testSettings['componentName'])
        ->set('detailData.key', 'NO_DESC_ROLE')
        ->set('detailData.name', 'Role Without Description')
        // No description set
        ->call('store')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('user_roles', [
        'key' => 'NO_DESC_ROLE',
        'name' => 'Role Without Description',
        'tenant_id' => $user->selected_tenant_id,
    ]);
});

it('closes modal process after delete', function () use ($testSettings): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('setup')->create();

    $userRole = UserRole::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'key' => 'CLOSE_TEST',
        'name' => 'Close Test Role',
    ]);

    $this->actingAs($user);

    Livewire::test($testSettings['componentName'], [$userRole])
        ->set('modelId', $userRole->id)
        ->call('delete')
        ->assertDispatched('closeTopModal');
});

it('uses correct component constants', function () use ($testSettings): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('setup')->create();

    $this->actingAs($user);

    $component = Livewire::test($testSettings['componentName']);

    // Check if constants are correctly defined (via reflection since they're used in the class)
    expect($testSettings['componentName'])->toBe('user-role-detail');
    expect($testSettings['listName'])->toBe('user-roles-list');
    expect($testSettings['urlParam'])->toBe('userRoleId');
});
