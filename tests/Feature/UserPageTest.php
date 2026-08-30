<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Enums\Profile;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 | The user editor is a page (noerd-user-page) hosting the form as an embedded
 | detail. The password form is no longer part of that form — it lives on its
 | own tab panel of the page.
 */

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->admin = NoerdUser::factory()->create(['super_admin' => false]);
    $this->admin->tenants()->attach($this->tenant->id, ['profile_key' => Profile::Admin->value]);

    TenantHelper::setSelectedTenantId($this->tenant->id);
    TenantHelper::setSelectedApp('SETUP');

    $this->actingAs($this->admin);
});

it('hosts the user form as an embedded detail', function (): void {
    Livewire::test('noerd::noerd-user-page', ['modelId' => $this->admin->id])
        ->assertOk()
        ->assertSeeLivewire('noerd::noerd-user-detail');
});

it('renders the password form on the page, not inside the user form', function (): void {
    Livewire::test('noerd::noerd-user-page', ['modelId' => $this->admin->id])
        ->assertOk()
        ->assertSeeLivewire('noerd::user-update-password');

    Livewire::test('noerd::noerd-user-detail', ['modelId' => $this->admin->id])
        ->assertOk()
        ->assertDontSeeLivewire('noerd::user-update-password');
});

it('renders no password form before the account exists', function (): void {
    Livewire::test('noerd::noerd-user-page')
        ->assertOk()
        ->assertDontSeeLivewire('noerd::user-update-password');
});

it('forwards the save to the embedded detail', function (): void {
    Livewire::test('noerd::noerd-user-page', ['modelId' => $this->admin->id])
        ->call('store')
        ->assertDispatched('storeDetail-noerd::noerd-user-detail');
});

it('deletes through the page like the detail does', function (): void {
    $member = NoerdUser::factory()->create();
    $member->tenants()->attach($this->tenant->id, ['profile_key' => Profile::User->value]);

    Livewire::test('noerd::noerd-user-page', ['modelId' => $member->id])
        ->call('delete');

    expect(NoerdUser::find($member->id))->toBeNull();
});

it('never deletes an account of a foreign tenant through the page', function (): void {
    $foreignTenant = Tenant::factory()->create();
    $foreign = NoerdUser::factory()->create();
    $foreign->tenants()->attach($foreignTenant->id, ['profile_key' => Profile::User->value]);

    try {
        Livewire::test('noerd::noerd-user-page', ['modelId' => $foreign->id])->call('delete');
    } catch (Throwable) {
        // A 403 may surface as an exception or a response; survival is the assertion.
    }

    expect(NoerdUser::find($foreign->id))->not->toBeNull();
});
