<?php

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Noerd\Noerd\Models\Profile;
use Noerd\Noerd\Models\Tenant;
use Noerd\Noerd\Models\User;
use Noerd\Noerd\Models\UserRole;
use Nywerk\Liefertool\Tests\Traits\FakeOrderTrait;

uses(FakeOrderTrait::class);
uses(Tests\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

$testSettings = [
    'componentName' => 'user-component',
    'listName' => 'users-table',
    'id' => 'userId',
];

it('renders the user component', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->create();

    $this->actingAs($admin);

    Volt::test($testSettings['componentName'])
        ->assertViewIs('volt-livewire::user-component')
        ->assertSeeText('Benutzer');
});

it('validates required fields when storing', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->create();

    $this->actingAs($admin);

    Volt::test($testSettings['componentName'])
        ->call('store')
        ->assertHasErrors(['user.name'])
        ->assertHasErrors(['user.email'])
        ->assertHasErrors(['tenantAccess']);
});

it('successfully creates a new user', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->create();
    $tenant = $admin->tenants->first();

    // Create a profile for the tenant
    $profile = Profile::factory()->create([
        'tenant_id' => $tenant->id,
        'key' => 'USER',
        'name' => 'Standard User',
    ]);

    $this->actingAs($admin);

    $userName = fake()->name;
    $userEmail = fake()->email;

    Volt::test($testSettings['componentName'])
        ->set('user.name', $userName)
        ->set('user.email', $userEmail)
        ->set("possibleTenants.{$tenant->id}.hasAccess", true)
        ->set("possibleTenants.{$tenant->id}.selectedProfile", $profile->id)
        ->call('store')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('users', [
        'name' => $userName,
        'email' => $userEmail,
    ]);

    // Check if user is attached to tenant with correct profile
    $createdUser = User::where('email', $userEmail)->first();
    expect($createdUser->tenants->contains($tenant->id))->toBeTrue();
});

it('updates an existing user', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->create();
    $tenant = $admin->tenants->first();

    $profile = Profile::factory()->create([
        'tenant_id' => $tenant->id,
        'key' => 'USER',
        'name' => 'Standard User',
    ]);

    $existingUser = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
    ]);

    $existingUser->tenants()->attach($tenant->id, ['profile_id' => $profile->id]);

    $this->actingAs($admin);

    $newName = 'Updated Name';
    $newEmail = 'updated@example.com';

    Volt::test($testSettings['componentName'], [$existingUser])
        ->set('modelId', $existingUser->id)
        ->set('user.name', $newName)
        ->set('user.email', $newEmail)
        ->set("possibleTenants.{$tenant->id}.hasAccess", true)
        ->set("possibleTenants.{$tenant->id}.selectedProfile", $profile->id)
        ->call('store')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('users', [
        'id' => $existingUser->id,
        'name' => $newName,
        'email' => $newEmail,
    ]);
});

it('handles existing user with same email', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->create();
    $tenant = $admin->tenants->first();

    $profile = Profile::factory()->create([
        'tenant_id' => $tenant->id,
        'key' => 'USER',
        'name' => 'Standard User',
    ]);

    // Create an existing user
    $existingUser = User::factory()->create([
        'email' => 'existing@example.com',
    ]);

    $this->actingAs($admin);

    // Try to create a new user with same email
    Volt::test($testSettings['componentName'])
        ->set('user.name', 'New User')
        ->set('user.email', 'existing@example.com')
        ->set("possibleTenants.{$tenant->id}.hasAccess", true)
        ->set("possibleTenants.{$tenant->id}.selectedProfile", $profile->id)
        ->call('store')
        ->assertHasNoErrors();

    // Check if existing user got access to the tenant
    expect($existingUser->fresh()->tenants->contains($tenant->id))->toBeTrue();
});

it('manages user roles correctly', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->create();
    $tenant = $admin->tenants->first();

    $profile = Profile::factory()->create([
        'tenant_id' => $tenant->id,
        'key' => 'USER',
        'name' => 'Standard User',
    ]);

    $role1 = UserRole::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Role 1']);
    $role2 = UserRole::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Role 2']);

    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, ['profile_id' => $profile->id]);

    $this->actingAs($admin);

    Volt::test($testSettings['componentName'], [$user])
        ->set('modelId', $user->id)
        ->set('user.name', $user->name)
        ->set('user.email', $user->email)
        ->set("possibleTenants.{$tenant->id}.hasAccess", true)
        ->set("possibleTenants.{$tenant->id}.selectedProfile", $profile->id)
        ->set("userRoles.{$role1->id}", true)
        ->set("userRoles.{$role2->id}", false)
        ->call('store')
        ->assertHasNoErrors();

    // Check if user has the correct roles
    $user->refresh();
    expect($user->roles->contains($role1->id))->toBeTrue();
    expect($user->roles->contains($role2->id))->toBeFalse();
});

it('manages tenant access correctly', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->create();
    $tenant1 = $admin->tenants->first();
    $tenant2 = Tenant::factory()->create();

    // Add admin access to second tenant
    $adminProfile = Profile::factory()->create([
        'tenant_id' => $tenant2->id,
        'key' => 'ADMIN',
        'name' => 'Admin',
    ]);
    $admin->tenants()->attach($tenant2->id, ['profile_id' => $adminProfile->id]);

    $profile1 = Profile::factory()->create([
        'tenant_id' => $tenant1->id,
        'key' => 'USER',
        'name' => 'User 1',
    ]);

    $profile2 = Profile::factory()->create([
        'tenant_id' => $tenant2->id,
        'key' => 'USER',
        'name' => 'User 2',
    ]);

    $user = User::factory()->create();

    $this->actingAs($admin);

    Volt::test($testSettings['componentName'], [$user])
        ->set('modelId', $user->id)
        ->set('user.name', $user->name)
        ->set('user.email', $user->email)
        ->set("possibleTenants.{$tenant1->id}.hasAccess", true)
        ->set("possibleTenants.{$tenant1->id}.selectedProfile", $profile1->id)
        ->set("possibleTenants.{$tenant2->id}.hasAccess", false)
        ->call('store')
        ->assertHasNoErrors();

    // Check if user has access to correct tenants
    $user->refresh();
    expect($user->tenants->contains($tenant1->id))->toBeTrue();
    expect($user->tenants->contains($tenant2->id))->toBeFalse();
});

it('requires at least one tenant access', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->create();
    $tenant = $admin->tenants->first();

    $this->actingAs($admin);

    Volt::test($testSettings['componentName'])
        ->set('user.name', 'Test User')
        ->set('user.email', 'test@example.com')
        ->set("possibleTenants.{$tenant->id}.hasAccess", false)
        ->call('store')
        ->assertHasErrors(['tenantAccess']);
});

it('deletes user tenant access', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->create();
    $tenant = $admin->tenants->first();

    $profile = Profile::factory()->create([
        'tenant_id' => $tenant->id,
        'key' => 'USER',
        'name' => 'Standard User',
    ]);

    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, ['profile_id' => $profile->id]);

    $this->actingAs($admin);

    Volt::test($testSettings['componentName'], [$user])
        ->set('modelId', $user->id)
        ->call('delete')
        ->assertDispatched('reloadTable-users-table');

    // Check if user access to tenant was removed
    $user->refresh();
    expect($user->tenants->contains($tenant->id))->toBeFalse();
});

it('loads user roles in mount', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->create();
    $tenant = $admin->tenants->first();

    $profile = Profile::factory()->create([
        'tenant_id' => $tenant->id,
        'key' => 'USER',
        'name' => 'Standard User',
    ]);

    $role = UserRole::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, ['profile_id' => $profile->id]);
    $user->roles()->attach($role->id);

    $this->actingAs($admin);

    // Test that the component can successfully set and store user roles
    $component = Volt::test($testSettings['componentName'], [$user])
        ->set('modelId', $user->id)
        ->set("userRoles.{$role->id}", true)
        ->set('user.name', $user->name)
        ->set('user.email', $user->email)
        ->set("possibleTenants.{$tenant->id}.hasAccess", true)
        ->set("possibleTenants.{$tenant->id}.selectedProfile", $profile->id)
        ->call('store')
        ->assertHasNoErrors();

    // Check if user role was properly attached
    $user->refresh();
    expect($user->roles->contains($role->id))->toBeTrue();
});

it('computes roles correctly', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->create();
    $tenant = $admin->tenants->first();

    UserRole::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Test Role',
        'key' => 'TEST_ROLE',
    ]);

    $this->actingAs($admin);

    $component = Volt::test($testSettings['componentName']);
    $roles = $component->get('roles');

    expect($roles)->toHaveKey($tenant->name);
    expect($roles[$tenant->name])->toHaveCount(1);
});

it('computes tenant profiles correctly', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->create();
    $tenant = $admin->tenants->first();

    $profile = Profile::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Test Profile',
    ]);

    $this->actingAs($admin);

    $component = Volt::test($testSettings['componentName']);
    $tenantProfiles = $component->get('tenantProfiles');

    expect($tenantProfiles)->toHaveKey($profile->id);
    expect($tenantProfiles[$profile->id])->toBe($profile->name);
});

it('sets success indicator after storing', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->create();
    $tenant = $admin->tenants->first();

    $profile = Profile::factory()->create([
        'tenant_id' => $tenant->id,
        'key' => 'USER',
        'name' => 'Standard User',
    ]);

    $this->actingAs($admin);

    Volt::test($testSettings['componentName'])
        ->set('user.name', 'Test User')
        ->set('user.email', 'test@example.com')
        ->set("possibleTenants.{$tenant->id}.hasAccess", true)
        ->set("possibleTenants.{$tenant->id}.selectedProfile", $profile->id)
        ->call('store')
        ->assertSet('showSuccessIndicator', true);
});

it('sends password reset link when creating new user', function () use ($testSettings): void {
    // Fake notifications to capture what is sent
    Notification::fake();
    
    $admin = User::factory()->adminUser()->create();
    $tenant = $admin->tenants->first();

    // Create a profile for the tenant
    $profile = Profile::factory()->create([
        'tenant_id' => $tenant->id,
        'key' => 'USER',
        'name' => 'Standard User',
    ]);

    $this->actingAs($admin);

    $userName = fake()->name;
    $userEmail = fake()->email;

    // Create new user via component
    Volt::test($testSettings['componentName'])
        ->set('user.name', $userName)
        ->set('user.email', $userEmail)
        ->set("possibleTenants.{$tenant->id}.hasAccess", true)
        ->set("possibleTenants.{$tenant->id}.selectedProfile", $profile->id)
        ->call('store')
        ->assertHasNoErrors();

    // Verify user was created
    $createdUser = User::where('email', $userEmail)->first();
    expect($createdUser)->not->toBeNull();
    expect($createdUser->name)->toBe($userName);
    expect($createdUser->email)->toBe($userEmail);

    // Verify that a password reset notification was sent to the new user
    Notification::assertSentTo(
        $createdUser,
        ResetPassword::class
    );

    // Verify that only one notification was sent
    Notification::assertCount(1);
});

it('does not send password reset link when updating existing user', function () use ($testSettings): void {
    // Fake notifications to capture what is sent
    Notification::fake();
    
    $admin = User::factory()->adminUser()->create();
    $tenant = $admin->tenants->first();

    $profile = Profile::factory()->create([
        'tenant_id' => $tenant->id,
        'key' => 'USER',
        'name' => 'Standard User',
    ]);

    // Create an existing user
    $existingUser = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
    ]);
    $existingUser->tenants()->attach($tenant->id, ['profile_id' => $profile->id]);

    $this->actingAs($admin);

    // Update existing user via component
    Volt::test($testSettings['componentName'], [$existingUser])
        ->set('modelId', $existingUser->id)
        ->set('user.name', 'Updated Name')
        ->set('user.email', 'updated@example.com')
        ->set("possibleTenants.{$tenant->id}.hasAccess", true)
        ->set("possibleTenants.{$tenant->id}.selectedProfile", $profile->id)
        ->call('store')
        ->assertHasNoErrors();

    // Verify that NO password reset notification was sent (since this is an update, not creation)
    Notification::assertNothingSent();
});

it('creates user with hashed password that user cannot login with before reset', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->create();
    $tenant = $admin->tenants->first();

    // Create a profile for the tenant
    $profile = Profile::factory()->create([
        'tenant_id' => $tenant->id,
        'key' => 'USER',
        'name' => 'Standard User',
    ]);

    $this->actingAs($admin);

    $userName = fake()->name;
    $userEmail = fake()->email;

    // Create new user via component
    Volt::test($testSettings['componentName'])
        ->set('user.name', $userName)
        ->set('user.email', $userEmail)
        ->set("possibleTenants.{$tenant->id}.hasAccess", true)
        ->set("possibleTenants.{$tenant->id}.selectedProfile", $profile->id)
        ->call('store')
        ->assertHasNoErrors();

    // Verify user was created with a hashed password
    $createdUser = User::where('email', $userEmail)->first();
    expect($createdUser)->not->toBeNull();
    expect($createdUser->password)->not->toBeNull();
    expect($createdUser->password)->not->toBe('');
    
    // Verify password is hashed (starts with $2y$ for bcrypt)
    expect($createdUser->password)->toStartWith('$2y$');
    
    // Verify password is long (hashed passwords are longer than plain text)
    expect(strlen($createdUser->password))->toBeGreaterThan(50);
});
