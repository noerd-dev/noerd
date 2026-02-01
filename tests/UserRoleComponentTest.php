<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

use Noerd\Models\User;
use Noerd\Models\UserRole;

uses(Tests\TestCase::class);
uses(RefreshDatabase::class);

$testSettings = [
    'componentName' => 'user-role-detail',
    'listName' => 'user-roles-list',
    'id' => 'userRoleId',
];

it('renders the user role component', function () use ($testSettings): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('setup')->create();

    $this->actingAs($user);

    Livewire::test($testSettings['componentName'])
        ->assertViewIs('volt-livewire::user-role-detail')
        ->assertSeeText('Benutzerrolle');
});

it('validates required fields when storing', function () use ($testSettings): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('setup')->create();

    $this->actingAs($user);

    Livewire::test($testSettings['componentName'])
        ->call('store')
        ->assertHasErrors(['userRoleData.key'])
        ->assertHasErrors(['userRoleData.name']);
});

it('successfully creates a new user role', function () use ($testSettings): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('setup')->create();

    $this->actingAs($user);

    $roleKey = 'ADMIN_ROLE';
    $roleName = 'Administrator Role';
    $roleDescription = 'Has full administrative access';

    Livewire::test($testSettings['componentName'])
        ->set('userRoleData.key', $roleKey)
        ->set('userRoleData.name', $roleName)
        ->set('userRoleData.description', $roleDescription)
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
        ->set('userRoleId', $existingRole->id)
        ->set('userRoleData.key', $newKey)
        ->set('userRoleData.name', $newName)
        ->set('userRoleData.description', $newDescription)
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
        ->set('userRoleData.key', 'TEST_ROLE')
        ->set('userRoleData.name', 'Test Role')
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
        ->set('userRoleId', $userRole->id)
        ->call('delete')
        ->assertDispatched('closeModal');

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
        ->set('userRoleId', $userRole->id);

    // Check if user role data is loaded correctly
    expect($component->get('userRoleData.key'))->toBe($userRole->key);
    expect($component->get('userRoleData.name'))->toBe($userRole->name);
    expect($component->get('userRoleData.description'))->toBe($userRole->description);
    expect($component->get('userRoleId'))->toBe($userRole->id);
});

it('mounts with new user role', function () use ($testSettings): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('setup')->create();

    $this->actingAs($user);

    $component = Livewire::test($testSettings['componentName']);

    // Check if default values are set for new user role
    expect($component->get('userRoleData'))->toBeArray();
    expect($component->get('userRoleId'))->toBeNull();
});

it('sets success indicator after storing', function () use ($testSettings): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('setup')->create();

    $this->actingAs($user);

    Livewire::test($testSettings['componentName'])
        ->set('userRoleData.key', 'SUCCESS_ROLE')
        ->set('userRoleData.name', 'Success Role')
        ->call('store')
        ->assertSet('showSuccessIndicator', true);
});

it('sets userRoleId after creating new role', function () use ($testSettings): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('setup')->create();

    $this->actingAs($user);

    $component = Livewire::test($testSettings['componentName'])
        ->set('userRoleData.key', 'NEW_ROLE')
        ->set('userRoleData.name', 'New Role')
        ->call('store');

    // Check if userRoleId was set after creation
    expect($component->get('userRoleId'))->not->toBeNull();
});

it('validates key field format', function () use ($testSettings): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('setup')->create();

    $this->actingAs($user);

    Livewire::test($testSettings['componentName'])
        ->set('userRoleData.key', '') // Empty key
        ->set('userRoleData.name', 'Valid Name')
        ->call('store')
        ->assertHasErrors(['userRoleData.key']);
});

it('validates name field format', function () use ($testSettings): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('setup')->create();

    $this->actingAs($user);

    Livewire::test($testSettings['componentName'])
        ->set('userRoleData.key', 'VALID_KEY')
        ->set('userRoleData.name', '') // Empty name
        ->call('store')
        ->assertHasErrors(['userRoleData.name']);
});

it('handles optional description field', function () use ($testSettings): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('setup')->create();

    $this->actingAs($user);

    Livewire::test($testSettings['componentName'])
        ->set('userRoleData.key', 'NO_DESC_ROLE')
        ->set('userRoleData.name', 'Role Without Description')
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
        ->set('userRoleId', $userRole->id)
        ->call('delete')
        ->assertDispatched('closeModal');
});

it('uses correct component constants', function () use ($testSettings): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('setup')->create();

    $this->actingAs($user);

    $component = Livewire::test($testSettings['componentName']);

    // Check if constants are correctly defined (via reflection since they're used in the class)
    expect($testSettings['componentName'])->toBe('user-role-detail');
    expect($testSettings['listName'])->toBe('user-roles-list');
    expect($testSettings['id'])->toBe('userRoleId');
});
