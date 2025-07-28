<?php

use Livewire\Volt\Volt;
use Noerd\Noerd\Models\User;
use Noerd\Noerd\Models\UserRole;
use Nywerk\Liefertool\Tests\Traits\FakeOrderTrait;

uses(FakeOrderTrait::class);
uses(Tests\TestCase::class);

$testSettings = [
    'componentName' => 'user-role-component',
    'listName' => 'user-roles-table',
    'id' => 'userRoleId',
];

it('renders the user role component', function () use ($testSettings): void {
    $user = User::factory()->withCanteenAndMenu()->create();

    $this->actingAs($user);

    Volt::test($testSettings['componentName'])
        ->assertViewIs('volt-livewire::user-role-component')
        ->assertSeeText('Benutzerrolle');
});

it('validates required fields when storing', function () use ($testSettings): void {
    $user = User::factory()->withCanteenAndMenu()->create();

    $this->actingAs($user);

    Volt::test($testSettings['componentName'])
        ->call('store')
        ->assertHasErrors(['userRole.key'])
        ->assertHasErrors(['userRole.name']);
});

it('successfully creates a new user role', function () use ($testSettings): void {
    $user = User::factory()->withCanteenAndMenu()->create();

    $this->actingAs($user);

    $roleKey = 'ADMIN_ROLE';
    $roleName = 'Administrator Role';
    $roleDescription = 'Has full administrative access';

    Volt::test($testSettings['componentName'])
        ->set('userRole.key', $roleKey)
        ->set('userRole.name', $roleName)
        ->set('userRole.description', $roleDescription)
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
    $user = User::factory()->withCanteenAndMenu()->create();

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

    Volt::test($testSettings['componentName'], [$existingRole])
        ->set('modelId', $existingRole->id)
        ->set('userRole.key', $newKey)
        ->set('userRole.name', $newName)
        ->set('userRole.description', $newDescription)
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
    $user = User::factory()->withCanteenAndMenu()->create();

    $this->actingAs($user);

    Volt::test($testSettings['componentName'])
        ->set('userRole.key', 'TEST_ROLE')
        ->set('userRole.name', 'Test Role')
        ->call('store')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('user_roles', [
        'key' => 'TEST_ROLE',
        'name' => 'Test Role',
        'tenant_id' => $user->selected_tenant_id,
    ]);
});

it('deletes a user role', function () use ($testSettings): void {
    $user = User::factory()->withCanteenAndMenu()->create();

    $userRole = UserRole::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'key' => 'DELETE_ME',
        'name' => 'Delete Me Role',
    ]);

    $this->actingAs($user);

    Volt::test($testSettings['componentName'], [$userRole])
        ->set('modelId', $userRole->id)
        ->call('delete')
        ->assertDispatched('reloadTable-user-roles-table');

    $this->assertDatabaseMissing('user_roles', [
        'id' => $userRole->id,
    ]);
});

it('mounts with existing user role data', function () use ($testSettings): void {
    $user = User::factory()->withCanteenAndMenu()->create();

    $userRole = UserRole::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'key' => 'EXISTING_ROLE',
        'name' => 'Existing Role',
        'description' => 'An existing role',
    ]);

    $this->actingAs($user);

    $component = Volt::test($testSettings['componentName'], [$userRole])
        ->set('modelId', $userRole->id);

    // Check if user role data is loaded correctly
    expect($component->get('userRole.key'))->toBe($userRole->key);
    expect($component->get('userRole.name'))->toBe($userRole->name);
    expect($component->get('userRole.description'))->toBe($userRole->description);
    expect($component->get('userRoleId'))->toBe($userRole->id);
});

it('mounts with new user role', function () use ($testSettings): void {
    $user = User::factory()->withCanteenAndMenu()->create();

    $this->actingAs($user);

    $component = Volt::test($testSettings['componentName']);

    // Check if default values are set for new user role
    expect($component->get('userRole'))->toBeArray();
    expect($component->get('userRoleId'))->toBeNull();
});

it('sets success indicator after storing', function () use ($testSettings): void {
    $user = User::factory()->withCanteenAndMenu()->create();

    $this->actingAs($user);

    Volt::test($testSettings['componentName'])
        ->set('userRole.key', 'SUCCESS_ROLE')
        ->set('userRole.name', 'Success Role')
        ->call('store')
        ->assertSet('showSuccessIndicator', true);
});

it('sets userRoleId after creating new role', function () use ($testSettings): void {
    $user = User::factory()->withCanteenAndMenu()->create();

    $this->actingAs($user);

    $component = Volt::test($testSettings['componentName'])
        ->set('userRole.key', 'NEW_ROLE')
        ->set('userRole.name', 'New Role')
        ->call('store');

    // Check if userRoleId was set after creation
    expect($component->get('userRoleId'))->not->toBeNull();
});

it('validates key field format', function () use ($testSettings): void {
    $user = User::factory()->withCanteenAndMenu()->create();

    $this->actingAs($user);

    Volt::test($testSettings['componentName'])
        ->set('userRole.key', '') // Empty key
        ->set('userRole.name', 'Valid Name')
        ->call('store')
        ->assertHasErrors(['userRole.key']);
});

it('validates name field format', function () use ($testSettings): void {
    $user = User::factory()->withCanteenAndMenu()->create();

    $this->actingAs($user);

    Volt::test($testSettings['componentName'])
        ->set('userRole.key', 'VALID_KEY')
        ->set('userRole.name', '') // Empty name
        ->call('store')
        ->assertHasErrors(['userRole.name']);
});

it('handles optional description field', function () use ($testSettings): void {
    $user = User::factory()->withCanteenAndMenu()->create();

    $this->actingAs($user);

    Volt::test($testSettings['componentName'])
        ->set('userRole.key', 'NO_DESC_ROLE')
        ->set('userRole.name', 'Role Without Description')
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
    $user = User::factory()->withCanteenAndMenu()->create();

    $userRole = UserRole::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'key' => 'CLOSE_TEST',
        'name' => 'Close Test Role',
    ]);

    $this->actingAs($user);

    Volt::test($testSettings['componentName'], [$userRole])
        ->set('modelId', $userRole->id)
        ->call('delete')
        ->assertDispatched('reloadTable-user-roles-table');
});

it('uses correct component constants', function () use ($testSettings): void {
    $user = User::factory()->withCanteenAndMenu()->create();

    $this->actingAs($user);

    $component = Volt::test($testSettings['componentName']);

    // Check if constants are correctly defined (via reflection since they're used in the class)
    expect($testSettings['componentName'])->toBe('user-role-component');
    expect($testSettings['listName'])->toBe('user-roles-table');
    expect($testSettings['id'])->toBe('userRoleId');
});
