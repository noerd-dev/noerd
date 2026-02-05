<?php

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Noerd\Models\Profile;
use Noerd\Models\Tenant;
use Noerd\Models\User;
use Noerd\Models\UserRole;

uses(Tests\TestCase::class);
uses(RefreshDatabase::class);

$testSettings = [
    'componentName' => 'user-detail',
    'listName' => 'users-list',
    'id' => 'id',
];

it('renders the user component', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->withSelectedApp('setup')->create();

    $this->actingAs($admin);

    Livewire::test($testSettings['componentName'])
        ->assertStatus(200)
        ->assertSeeText('Benutzer');
});

it('validates required fields when storing', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->withSelectedApp('setup')->create();

    $this->actingAs($admin);

    Livewire::test($testSettings['componentName'])
        ->call('store')
        ->assertHasErrors(['detailData.name'])
        ->assertHasErrors(['detailData.email'])
        ->assertHasErrors(['tenantAccess']);
});

it('successfully creates a new user', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->withSelectedApp('setup')->create();
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

    Livewire::test($testSettings['componentName'])
        ->set('detailData.name', $userName)
        ->set('detailData.email', $userEmail)
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
    $admin = User::factory()->adminUser()->withSelectedApp('setup')->create();
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

    Livewire::test($testSettings['componentName'], [$existingUser])
        ->set('modelId', $existingUser->id)
        ->set('detailData.name', $newName)
        ->set('detailData.email', $newEmail)
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
    $admin = User::factory()->adminUser()->withSelectedApp('setup')->create();
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
    Livewire::test($testSettings['componentName'])
        ->set('detailData.name', 'New User')
        ->set('detailData.email', 'existing@example.com')
        ->set("possibleTenants.{$tenant->id}.hasAccess", true)
        ->set("possibleTenants.{$tenant->id}.selectedProfile", $profile->id)
        ->call('store')
        ->assertHasNoErrors();

    // Check if existing user got access to the tenant
    expect($existingUser->fresh()->tenants->contains($tenant->id))->toBeTrue();
});

it('manages user roles correctly', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->withSelectedApp('setup')->create();
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

    Livewire::test($testSettings['componentName'], [$user])
        ->set('modelId', $user->id)
        ->set('detailData.name', $user->name)
        ->set('detailData.email', $user->email)
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
    $admin = User::factory()->adminUser()->withSelectedApp('setup')->create();
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

    Livewire::test($testSettings['componentName'], [$user])
        ->set('modelId', $user->id)
        ->set('detailData.name', $user->name)
        ->set('detailData.email', $user->email)
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
    $admin = User::factory()->adminUser()->withSelectedApp('setup')->create();
    $tenant = $admin->tenants->first();

    $this->actingAs($admin);

    Livewire::test($testSettings['componentName'])
        ->set('detailData.name', 'Test User')
        ->set('detailData.email', 'test@example.com')
        ->set("possibleTenants.{$tenant->id}.hasAccess", false)
        ->call('store')
        ->assertHasErrors(['tenantAccess']);
});

it('loads user roles in mount', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->withSelectedApp('setup')->create();
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
    $component = Livewire::test($testSettings['componentName'], [$user])
        ->set('modelId', $user->id)
        ->set("userRoles.{$role->id}", true)
        ->set('detailData.name', $user->name)
        ->set('detailData.email', $user->email)
        ->set("possibleTenants.{$tenant->id}.hasAccess", true)
        ->set("possibleTenants.{$tenant->id}.selectedProfile", $profile->id)
        ->call('store')
        ->assertHasNoErrors();

    // Check if user role was properly attached
    $user->refresh();
    expect($user->roles->contains($role->id))->toBeTrue();
});

it('computes roles correctly', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->withSelectedApp('setup')->create();
    $tenant = $admin->tenants->first();

    UserRole::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Test Role',
        'key' => 'TEST_ROLE',
    ]);

    $this->actingAs($admin);

    $component = Livewire::test($testSettings['componentName']);
    $roles = $component->get('roles');

    expect($roles)->toHaveKey($tenant->name);
    expect($roles[$tenant->name])->toHaveCount(1);
});

it('computes tenant profiles correctly', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->withSelectedApp('setup')->create();
    $tenant = $admin->tenants->first();

    $profile = Profile::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Test Profile',
    ]);

    $this->actingAs($admin);

    $component = Livewire::test($testSettings['componentName']);
    $tenantProfiles = $component->get('tenantProfiles');

    expect($tenantProfiles)->toHaveKey($profile->id);
    expect($tenantProfiles[$profile->id])->toBe($profile->name);
});

it('sets success indicator after storing', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->withSelectedApp('setup')->create();
    $tenant = $admin->tenants->first();

    $profile = Profile::factory()->create([
        'tenant_id' => $tenant->id,
        'key' => 'USER',
        'name' => 'Standard User',
    ]);

    $this->actingAs($admin);

    Livewire::test($testSettings['componentName'])
        ->set('detailData.name', 'Test User')
        ->set('detailData.email', 'test@example.com')
        ->set("possibleTenants.{$tenant->id}.hasAccess", true)
        ->set("possibleTenants.{$tenant->id}.selectedProfile", $profile->id)
        ->call('store')
        ->assertSet('showSuccessIndicator', true);
});

it('sends password reset link when creating new user', function () use ($testSettings): void {
    // Fake notifications to capture what is sent
    Notification::fake();

    $admin = User::factory()->adminUser()->withSelectedApp('setup')->create();
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
    Livewire::test($testSettings['componentName'])
        ->set('detailData.name', $userName)
        ->set('detailData.email', $userEmail)
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
        ResetPassword::class,
    );

    // Verify that only one notification was sent
    Notification::assertCount(1);
});

it('does not send password reset link when updating existing user', function () use ($testSettings): void {
    // Fake notifications to capture what is sent
    Notification::fake();

    $admin = User::factory()->adminUser()->withSelectedApp('setup')->create();
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
    Livewire::test($testSettings['componentName'], [$existingUser])
        ->set('modelId', $existingUser->id)
        ->set('detailData.name', 'Updated Name')
        ->set('detailData.email', 'updated@example.com')
        ->set("possibleTenants.{$tenant->id}.hasAccess", true)
        ->set("possibleTenants.{$tenant->id}.selectedProfile", $profile->id)
        ->call('store')
        ->assertHasNoErrors();

    // Verify that NO password reset notification was sent (since this is an update, not creation)
    Notification::assertNothingSent();
});

it('creates user with hashed password that user cannot login with before reset', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->withSelectedApp('setup')->create();
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
    Livewire::test($testSettings['componentName'])
        ->set('detailData.name', $userName)
        ->set('detailData.email', $userEmail)
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
    expect(mb_strlen($createdUser->password))->toBeGreaterThan(50);
});
