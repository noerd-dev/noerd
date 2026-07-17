<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $user = NoerdUser::factory()->create();
    $tenant = Tenant::factory()->create();
    $user->tenants()->attach($tenant->id);
    TenantHelper::setSelectedTenantId($tenant->id);
    $this->actingAs($user);
});

it('hides only the navigation on the first toggle step', function (): void {
    Livewire::test('noerd::layout.top-bar')
        ->call('setSidebarState', false, true)
        ->assertOk();

    expect(session('hide_sidebar'))->toBeTrue()
        ->and(session()->has('hide_appbar'))->toBeFalse();
});

it('hides the app bar as well on the second toggle step', function (): void {
    Livewire::test('noerd::layout.top-bar')
        ->call('setSidebarState', false, false)
        ->assertOk();

    expect(session('hide_sidebar'))->toBeTrue()
        ->and(session('hide_appbar'))->toBeTrue();
});

it('shows navigation and app bar again on the third toggle step', function (): void {
    session(['hide_sidebar' => true, 'hide_appbar' => true]);

    Livewire::test('noerd::layout.top-bar')
        ->call('setSidebarState', true, true)
        ->assertOk();

    expect(session()->has('hide_sidebar'))->toBeFalse()
        ->and(session()->has('hide_appbar'))->toBeFalse();
});
