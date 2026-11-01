<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Noerd\Enums\Profile;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Notifications\AddedToTenantNotification;
use Noerd\Notifications\NoerdResetPassword;
use Noerd\Services\ProfileRegistry;
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

it('lets a super admin change name and e-mail of an account', function () use ($testSettings): void {
    $super = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create(['super_admin' => true]);
    $tenant = $super->tenants->first();

    $existingUser = NoerdUser::factory()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
    ]);
    $existingUser->tenants()->attach($tenant->id, ['profile_key' => Profile::User->value]);

    $this->actingAs($super);

    Livewire::test($testSettings['componentName'], ['modelId' => $existingUser->id])
        ->assertSet('accountEditable', true)
        ->set('detailData.name', 'Updated Name')
        ->set('detailData.email', 'updated@example.com')
        ->call('store')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('noerd_users', [
        'id' => $existingUser->id,
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
    ]);
});

it('never lets a tenant admin change name or e-mail of an account', function () use ($testSettings): void {
    $admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
    $tenant = $admin->tenants->first();

    $existingUser = NoerdUser::factory()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
    ]);
    $existingUser->tenants()->attach($tenant->id, ['profile_key' => Profile::User->value]);

    $this->actingAs($admin);

    Livewire::test($testSettings['componentName'], ['modelId' => $existingUser->id])
        ->assertSet('accountEditable', false)
        ->set('detailData.name', 'Updated Name')
        ->set('detailData.email', 'attacker@example.com')
        ->set("possibleTenants.{$tenant->id}.selectedProfile", Profile::ReadOnly->value)
        ->call('store')
        ->assertHasNoErrors();

    $existingUser->refresh();

    expect($existingUser->name)->toBe('Old Name')
        ->and($existingUser->email)->toBe('old@example.com')
        // The membership is the tenant admin's to manage.
        ->and($existingUser->tenants->firstWhere('id', $tenant->id)->pivot->profile_key)->toBe(Profile::ReadOnly->value);
});

it('adds an existing account by its e-mail address and tells the person', function () use ($testSettings): void {
    Notification::fake();

    $admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
    $tenant = $admin->tenants->first();

    $existingUser = NoerdUser::factory()->create([
        'name' => 'Real Name',
        'email' => 'existing@example.com',
    ]);

    $this->actingAs($admin);

    // No name needed: the account exists, its name stays the person's.
    Livewire::test($testSettings['componentName'])
        ->set('detailData.email', 'existing@example.com')
        ->set("possibleTenants.{$tenant->id}.hasAccess", true)
        ->set("possibleTenants.{$tenant->id}.selectedProfile", Profile::User->value)
        ->call('store')
        ->assertHasNoErrors();

    expect($existingUser->fresh()->tenants->contains($tenant->id))->toBeTrue()
        ->and($existingUser->fresh()->name)->toBe('Real Name')
        ->and(NoerdUser::where('email', 'existing@example.com')->count())->toBe(1);

    Notification::assertSentTo(
        $existingUser,
        AddedToTenantNotification::class,
        fn(AddedToTenantNotification $notification): bool => $notification->tenantName === $tenant->name,
    );
    Notification::assertNotSentTo($existingUser, NoerdResetPassword::class);
});

it('assigns a profile a module registered', function () use ($testSettings): void {
    app(ProfileRegistry::class)->register('ZZ_PROFILE', 'Zz Profile');

    $admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
    $tenant = $admin->tenants->first();
    $member = NoerdUser::factory()->create();
    $member->tenants()->attach($tenant->id, ['profile_key' => Profile::User->value]);

    $this->actingAs($admin);

    Livewire::test($testSettings['componentName'], ['modelId' => $member->id])
        ->set("possibleTenants.{$tenant->id}.selectedProfile", 'ZZ_PROFILE')
        ->call('store')
        ->assertHasNoErrors();

    // A registered profile used to drop the membership silently.
    expect($member->fresh()->tenants->firstWhere('id', $tenant->id)?->pivot->profile_key)->toBe('ZZ_PROFILE');
});

it('refuses a profile nobody registered', function () use ($testSettings): void {
    $admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
    $tenant = $admin->tenants->first();

    $this->actingAs($admin);

    Livewire::test($testSettings['componentName'])
        ->set('detailData.name', 'Test User')
        ->set('detailData.email', 'test@example.com')
        ->set("possibleTenants.{$tenant->id}.hasAccess", true)
        ->set("possibleTenants.{$tenant->id}.selectedProfile", 'NOT_A_PROFILE')
        ->call('store')
        ->assertHasErrors(["possibleTenants.{$tenant->id}.selectedProfile"]);

    expect(NoerdUser::where('email', 'test@example.com')->exists())->toBeFalse();
});

it('manages the memberships of the administered tenants', function () use ($testSettings): void {
    $admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
    $tenant1 = $admin->tenants->first();
    $tenant2 = Tenant::factory()->create();
    $admin->tenants()->attach($tenant2->id, ['profile_key' => Profile::Admin->value]);

    $user = NoerdUser::factory()->create();
    $user->tenants()->attach($tenant2->id, ['profile_key' => Profile::User->value]);

    $this->actingAs($admin);

    Livewire::test($testSettings['componentName'], ['modelId' => $user->id])
        ->set("possibleTenants.{$tenant1->id}.hasAccess", true)
        ->set("possibleTenants.{$tenant1->id}.selectedProfile", Profile::User->value)
        ->set("possibleTenants.{$tenant2->id}.hasAccess", false)
        ->call('store')
        ->assertHasNoErrors();

    $user->refresh();
    expect($user->tenants->contains($tenant1->id))->toBeTrue()
        ->and($user->tenants->contains($tenant2->id))->toBeFalse();
});

it('keeps the account when its last membership is unticked', function () use ($testSettings): void {
    $admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
    $tenant = $admin->tenants->first();
    $user = NoerdUser::factory()->create();
    $user->tenants()->attach($tenant->id, ['profile_key' => Profile::User->value]);

    $this->actingAs($admin);

    Livewire::test($testSettings['componentName'], ['modelId' => $user->id])
        ->set("possibleTenants.{$tenant->id}.hasAccess", false)
        ->call('store')
        ->assertHasNoErrors();

    expect(NoerdUser::find($user->id))->not->toBeNull()
        ->and($user->fresh()->tenants)->toBeEmpty();
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
