<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Noerd\Models\User;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('sets the impersonating_from session key when logging in as user', function (): void {
    $admin = User::factory()->adminUser()->create();
    $targetUser = User::factory()->create();

    // Attach target user to the same tenant as admin
    $tenant = $admin->adminTenants()->first();
    $targetUser->tenants()->attach($tenant->id);

    $this->actingAs($admin);

    Livewire::test('setup.users-list')
        ->call('loginAsUser', $targetUser->id);

    expect(session('impersonating_from'))->toBe($admin->id);
});

it('shows the impersonation banner when session key exists', function (): void {
    $admin = User::factory()->adminUser()->create();
    $targetUser = User::factory()->create(['name' => 'Test Target']);

    session(['impersonating_from' => $admin->id]);
    $this->actingAs($targetUser);

    $component = Livewire::test('layout.impersonation-banner');

    $component->assertSee($targetUser->name);
});

it('does not show the impersonation banner without session key', function (): void {
    $user = User::factory()->create(['name' => 'Regular User']);
    $this->actingAs($user);

    Livewire::test('layout.impersonation-banner')
        ->assertSet('isImpersonating', false)
        ->assertDontSee($user->name);
});

it('restores original user when stopping impersonation', function (): void {
    $admin = User::factory()->adminUser()->create();
    $targetUser = User::factory()->create();

    session(['impersonating_from' => $admin->id]);
    $this->actingAs($targetUser);

    Livewire::test('layout.impersonation-banner')
        ->call('stopImpersonating')
        ->assertRedirect('/');

    expect(session('impersonating_from'))->toBeNull();
    expect(Auth::id())->toBe($admin->id);
});

it('shows the impersonation banner with correct state when session key exists', function (): void {
    $admin = User::factory()->adminUser()->create();
    $targetUser = User::factory()->create(['name' => 'Impersonated User']);

    session(['impersonating_from' => $admin->id]);
    $this->actingAs($targetUser);

    Livewire::test('layout.impersonation-banner')
        ->assertSet('isImpersonating', true)
        ->assertSet('userName', 'Impersonated User');
});

it('clears tenant session when logging in as user', function (): void {
    $admin = User::factory()->adminUser()->create();
    $targetUser = User::factory()->create();

    // Attach target user to the same tenant as admin
    $tenant = $admin->adminTenants()->first();
    $targetUser->tenants()->attach($tenant->id);

    $this->actingAs($admin);

    // Set tenant session before login
    session(['noerd.selected_tenant_id' => $tenant->id]);
    session(['noerd.selected_app' => 'some-app']);

    $response = Livewire::test('setup.users-list')
        ->call('loginAsUser', $targetUser->id)
        ->assertRedirect('/');

    // Verify the redirect response clears the session (via the redirect's session)
    $response->assertSessionMissing('noerd.selected_app');
});

it('clears tenant session when stopping impersonation', function (): void {
    $admin = User::factory()->adminUser()->create();
    $targetUser = User::factory()->create();

    session(['impersonating_from' => $admin->id]);
    session(['noerd.selected_app' => 'another-app']);

    $this->actingAs($targetUser);

    $response = Livewire::test('layout.impersonation-banner')
        ->call('stopImpersonating')
        ->assertRedirect('/');

    $response->assertSessionMissing('noerd.selected_app');
    expect(Auth::id())->toBe($admin->id);
});
