<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Noerd\Enums\Profile;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Notifications\NoerdResetPassword;
use Noerd\Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

$testSettings = [
    'componentName' => 'noerd::noerd-user-detail',
    'listName' => 'noerd::noerd-users-list',
    'modelId' => 'modelId',
    'urlParam' => 'userId',
];

it('renders the user component', function () use ($testSettings): void {
    $admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();

    $this->actingAs($admin);

    // Which fields (and their labels/options) render comes from the detail YAML —
    // per-installation configuration that must not be asserted here.
    Livewire::test($testSettings['componentName'])
        ->assertStatus(200)
        ->assertHasNoErrors();
});

it('validates required fields when storing', function () use ($testSettings): void {
    $admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();

    $this->actingAs($admin);

    Livewire::test($testSettings['componentName'])
        ->call('store')
        ->assertHasErrors(['detailData.name'])
        ->assertHasErrors(['detailData.email'])
        ->assertHasErrors(['tenantAccess']);
});

it('successfully creates a new user', function () use ($testSettings): void {
    $admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
    $tenant = $admin->tenants->first();

    // Create a profile for the tenant

    $this->actingAs($admin);

    $userName = fake()->name;
    $userEmail = fake()->email;

    Livewire::test($testSettings['componentName'])
        ->set('detailData.name', $userName)
        ->set('detailData.email', $userEmail)
        ->set("possibleTenants.{$tenant->id}.hasAccess", true)
        ->set("possibleTenants.{$tenant->id}.selectedProfile", Profile::User->value)
        ->call('store')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('noerd_users', [
        'name' => $userName,
        'email' => $userEmail,
    ]);

    // Check if user is attached to tenant with correct profile
    $createdUser = NoerdUser::where('email', $userEmail)->first();
    expect($createdUser->tenants->contains($tenant->id))->toBeTrue();
});

it('updates an existing user', function () use ($testSettings): void {
    $admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
    $tenant = $admin->tenants->first();


    $existingUser = NoerdUser::factory()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
    ]);

    $existingUser->tenants()->attach($tenant->id, ['profile_key' => Profile::User->value]);

    $this->actingAs($admin);

    $newName = 'Updated Name';
    $newEmail = 'updated@example.com';

    Livewire::test($testSettings['componentName'], [$existingUser])
        ->set('modelId', $existingUser->id)
        ->set('detailData.name', $newName)
        ->set('detailData.email', $newEmail)
        ->set("possibleTenants.{$tenant->id}.hasAccess", true)
        ->set("possibleTenants.{$tenant->id}.selectedProfile", Profile::User->value)
        ->call('store')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('noerd_users', [
        'id' => $existingUser->id,
        'name' => $newName,
        'email' => $newEmail,
    ]);
});

it('handles existing user with same email', function () use ($testSettings): void {
    $admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
    $tenant = $admin->tenants->first();


    // Create an existing user
    $existingUser = NoerdUser::factory()->create([
        'email' => 'existing@example.com',
    ]);

    $this->actingAs($admin);

    // Try to create a new user with same email
    Livewire::test($testSettings['componentName'])
        ->set('detailData.name', 'New User')
        ->set('detailData.email', 'existing@example.com')
        ->set("possibleTenants.{$tenant->id}.hasAccess", true)
        ->set("possibleTenants.{$tenant->id}.selectedProfile", Profile::User->value)
        ->call('store')
        ->assertHasNoErrors();

    // Check if existing user got access to the tenant
    expect($existingUser->fresh()->tenants->contains($tenant->id))->toBeTrue();
});

it('manages tenant access correctly', function () use ($testSettings): void {
    $admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
    $tenant1 = $admin->tenants->first();
    $tenant2 = Tenant::factory()->create();

    // Add admin access to second tenant
    $admin->tenants()->attach($tenant2->id, ['profile_key' => Profile::Admin->value]);



    $user = NoerdUser::factory()->create();

    $this->actingAs($admin);

    Livewire::test($testSettings['componentName'], [$user])
        ->set('modelId', $user->id)
        ->set('detailData.name', $user->name)
        ->set('detailData.email', $user->email)
        ->set("possibleTenants.{$tenant1->id}.hasAccess", true)
        ->set("possibleTenants.{$tenant1->id}.selectedProfile", Profile::User->value)
        ->set("possibleTenants.{$tenant2->id}.hasAccess", false)
        ->call('store')
        ->assertHasNoErrors();

    // Check if user has access to correct tenants
    $user->refresh();
    expect($user->tenants->contains($tenant1->id))->toBeTrue();
    expect($user->tenants->contains($tenant2->id))->toBeFalse();
});

it('requires at least one tenant access', function () use ($testSettings): void {
    $admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
    $tenant = $admin->tenants->first();

    $this->actingAs($admin);

    Livewire::test($testSettings['componentName'])
        ->set('detailData.name', 'Test User')
        ->set('detailData.email', 'test@example.com')
        ->set("possibleTenants.{$tenant->id}.hasAccess", false)
        ->call('store')
        ->assertHasErrors(['tenantAccess']);
});

it('rebuilds the tenant access map on every store', function () use ($testSettings): void {
    $admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
    $tenant = $admin->tenants->first();

    $this->actingAs($admin);

    // tenantAccess is client-writable: a stale/forged entry must not keep
    // satisfying the AtLeastOneTrue rule once every checkbox is unticked.
    Livewire::test($testSettings['componentName'])
        ->set('detailData.name', 'Test User')
        ->set('detailData.email', 'test@example.com')
        ->set('tenantAccess', [999999 => true])
        ->set("possibleTenants.{$tenant->id}.hasAccess", false)
        ->call('store')
        ->assertHasErrors(['tenantAccess'])
        ->assertSet('tenantAccess', [$tenant->id => false]);
});

it('sets success indicator after storing', function () use ($testSettings): void {
    $admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
    $tenant = $admin->tenants->first();


    $this->actingAs($admin);

    Livewire::test($testSettings['componentName'])
        ->set('detailData.name', 'Test User')
        ->set('detailData.email', 'test@example.com')
        ->set("possibleTenants.{$tenant->id}.hasAccess", true)
        ->set("possibleTenants.{$tenant->id}.selectedProfile", Profile::User->value)
        ->call('store')
        ->assertSet('showSuccessIndicator', true);
});

it('sends password reset link when creating new user', function () use ($testSettings): void {
    // Fake notifications to capture what is sent
    Notification::fake();

    $admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
    $tenant = $admin->tenants->first();

    // Create a profile for the tenant

    $this->actingAs($admin);

    $userName = fake()->name;
    $userEmail = fake()->email;

    // Create new user via component
    Livewire::test($testSettings['componentName'])
        ->set('detailData.name', $userName)
        ->set('detailData.email', $userEmail)
        ->set("possibleTenants.{$tenant->id}.hasAccess", true)
        ->set("possibleTenants.{$tenant->id}.selectedProfile", Profile::User->value)
        ->call('store')
        ->assertHasNoErrors();

    // Verify user was created
    $createdUser = NoerdUser::where('email', $userEmail)->first();
    expect($createdUser)->not->toBeNull();
    expect($createdUser->name)->toBe($userName);
    expect($createdUser->email)->toBe($userEmail);

    // Verify that a password reset notification was sent to the new user
    Notification::assertSentTo(
        $createdUser,
        NoerdResetPassword::class,
    );

    // Verify that only one notification was sent
    Notification::assertCount(1);
});

it('does not send password reset link when updating existing user', function () use ($testSettings): void {
    // Fake notifications to capture what is sent
    Notification::fake();

    $admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
    $tenant = $admin->tenants->first();


    // Create an existing user
    $existingUser = NoerdUser::factory()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
    ]);
    $existingUser->tenants()->attach($tenant->id, ['profile_key' => Profile::User->value]);

    $this->actingAs($admin);

    // Update existing user via component
    Livewire::test($testSettings['componentName'], [$existingUser])
        ->set('modelId', $existingUser->id)
        ->set('detailData.name', 'Updated Name')
        ->set('detailData.email', 'updated@example.com')
        ->set("possibleTenants.{$tenant->id}.hasAccess", true)
        ->set("possibleTenants.{$tenant->id}.selectedProfile", Profile::User->value)
        ->call('store')
        ->assertHasNoErrors();

    // Verify that NO password reset notification was sent (since this is an update, not creation)
    Notification::assertNothingSent();
});

it('creates user with hashed password that user cannot login with before reset', function () use ($testSettings): void {
    $admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
    $tenant = $admin->tenants->first();

    // Create a profile for the tenant

    $this->actingAs($admin);

    $userName = fake()->name;
    $userEmail = fake()->email;

    // Create new user via component
    Livewire::test($testSettings['componentName'])
        ->set('detailData.name', $userName)
        ->set('detailData.email', $userEmail)
        ->set("possibleTenants.{$tenant->id}.hasAccess", true)
        ->set("possibleTenants.{$tenant->id}.selectedProfile", Profile::User->value)
        ->call('store')
        ->assertHasNoErrors();

    // Verify user was created with a hashed password
    $createdUser = NoerdUser::where('email', $userEmail)->first();
    expect($createdUser)->not->toBeNull();
    expect($createdUser->password)->not->toBeNull();
    expect($createdUser->password)->not->toBe('');

    // Verify password is hashed (starts with $2y$ for bcrypt)
    expect($createdUser->password)->toStartWith('$2y$');

    // Verify password is long (hashed passwords are longer than plain text)
    expect(mb_strlen($createdUser->password))->toBeGreaterThan(50);
});

it('does not send password reset link when the option is disabled', function () use ($testSettings): void {
    Notification::fake();

    $admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
    $tenant = $admin->tenants->first();


    $this->actingAs($admin);

    $userEmail = fake()->email;

    Livewire::test($testSettings['componentName'])
        ->set('detailData.name', fake()->name)
        ->set('detailData.email', $userEmail)
        ->set('sendPasswordResetMail', false)
        ->set("possibleTenants.{$tenant->id}.hasAccess", true)
        ->set("possibleTenants.{$tenant->id}.selectedProfile", Profile::User->value)
        ->call('store')
        ->assertHasNoErrors();

    expect(NoerdUser::where('email', $userEmail)->first())->not->toBeNull();

    Notification::assertNothingSent();
});

it('stores the selected locale in the user settings when creating a new user', function () use ($testSettings): void {
    Notification::fake();

    $admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
    $tenant = $admin->tenants->first();


    $this->actingAs($admin);

    $userEmail = fake()->email;

    Livewire::test($testSettings['componentName'])
        ->set('detailData.name', fake()->name)
        ->set('detailData.email', $userEmail)
        ->set('userLocale', 'de')
        ->set("possibleTenants.{$tenant->id}.hasAccess", true)
        ->set("possibleTenants.{$tenant->id}.selectedProfile", Profile::User->value)
        ->call('store')
        ->assertHasNoErrors();

    $createdUser = NoerdUser::where('email', $userEmail)->first();

    $this->assertDatabaseHas('noerd_user_settings', [
        'user_id' => $createdUser->id,
        'locale' => 'de',
    ]);
    expect($createdUser->locale)->toBe('de');
});

it('prefers the user setting locale for notifications', function (): void {
    $user = NoerdUser::factory()->create();
    $user->locale = 'de';

    expect($user->fresh()->preferredLocale())->toBe('de');
});
