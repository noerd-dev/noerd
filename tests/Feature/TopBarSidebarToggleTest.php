<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $user = NoerdUser::factory()->create();
    $tenant = Tenant::factory()->create();
    $user->tenants()->attach($tenant->id);
    TenantHelper::setSelectedTenantId($tenant->id);
    $this->actingAs($user);
});

it('hides only the navigation without touching the app bar', function (): void {
    Livewire::test('noerd::layout.top-bar')
        ->call('setSidebarVisibility', false)
        ->assertOk();

    expect(session('hide_sidebar'))->toBeTrue()
        ->and(session()->has('hide_appbar'))->toBeFalse();
});

it('shows the navigation again without touching the app bar', function (): void {
    session(['hide_sidebar' => true, 'hide_appbar' => true]);

    Livewire::test('noerd::layout.top-bar')
        ->call('setSidebarVisibility', true)
        ->assertOk();

    expect(session()->has('hide_sidebar'))->toBeFalse()
        ->and(session('hide_appbar'))->toBeTrue();
});

it('hides the app bar without touching the navigation', function (): void {
    Livewire::test('noerd::layout.top-bar')
        ->call('setAppbarVisibility', false)
        ->assertOk();

    expect(session('hide_appbar'))->toBeTrue()
        ->and(session()->has('hide_sidebar'))->toBeFalse();
});

it('shows the app bar again without touching the navigation', function (): void {
    session(['hide_sidebar' => true, 'hide_appbar' => true]);

    Livewire::test('noerd::layout.top-bar')
        ->call('setAppbarVisibility', true)
        ->assertOk();

    expect(session()->has('hide_appbar'))->toBeFalse()
        ->and(session('hide_sidebar'))->toBeTrue();
});

it('opens home via the sidebar home shortcut', function (): void {
    Livewire::test('noerd::layout.sidebar')
        ->call('openHome')
        ->assertRedirect(route('noerd-apps'));

    expect(session('noerd.selected_app'))->toBe('noerd-apps');
});

it('toggles the app bar session state via the sidebar button', function (): void {
    Livewire::test('noerd::layout.sidebar')
        ->call('toggleAppbar')
        ->assertOk();

    expect(session('hide_appbar'))->toBeTrue();

    Livewire::test('noerd::layout.sidebar')
        ->call('toggleAppbar')
        ->assertOk();

    expect(session()->has('hide_appbar'))->toBeFalse();
});
