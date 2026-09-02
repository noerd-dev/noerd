<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('sets the impersonating_from session key when logging in as user', function (): void {
    $admin = NoerdUser::factory()->adminUser()->create();
    $targetUser = NoerdUser::factory()->create();

    // Attach target user to the same tenant as admin
    $tenant = $admin->adminTenants()->first();
    $targetUser->tenants()->attach($tenant->id);

    $this->actingAs($admin);

    // Set tenant session before login — impersonation must clear it, so the
    // impersonated account resolves its own tenant.
    session(['noerd.selected_tenant_id' => $tenant->id]);
    session(['noerd.selected_app' => 'some-app']);

    $response = Livewire::test('noerd::noerd-users-list')
        ->call('loginAsUser', $targetUser->id)
        ->assertRedirect('/');

    expect(session('impersonating_from'))->toBe($admin->id);

    $response->assertSessionMissing('noerd.selected_app');
});

it('does not show the impersonation banner without session key', function (): void {
    $user = NoerdUser::factory()->create(['name' => 'Regular User']);
    $this->actingAs($user);

    Livewire::test('noerd::layout.impersonation-banner')
        ->assertSet('isImpersonating', false)
        ->assertDontSee($user->name);
});

it('restores original user when stopping impersonation', function (): void {
    $admin = NoerdUser::factory()->adminUser()->create();
    $targetUser = NoerdUser::factory()->create();

    session(['impersonating_from' => $admin->id]);
    session(['noerd.selected_app' => 'another-app']);

    $this->actingAs($targetUser);

    $response = Livewire::test('noerd::layout.impersonation-banner')
        ->call('stopImpersonating')
        ->assertRedirect('/');

    expect(session('impersonating_from'))->toBeNull();

    $response->assertSessionMissing('noerd.selected_app');

    auth()->forgetGuards();
    expect(Auth::id())->toBe($admin->id);
});

it('shows the impersonation banner with correct state when session key exists', function (): void {
    $admin = NoerdUser::factory()->adminUser()->create();
    $targetUser = NoerdUser::factory()->create(['name' => 'Impersonated User']);

    session(['impersonating_from' => $admin->id]);
    $this->actingAs($targetUser);

    Livewire::test('noerd::layout.impersonation-banner')
        ->assertSet('isImpersonating', true)
        ->assertSet('userName', 'Impersonated User')
        ->assertSee('Impersonated User');
});
