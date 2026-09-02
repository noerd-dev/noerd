<?php

declare(strict_types=1);

use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Listeners\InitializeTenantSession;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Models\UserSetting;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('UserSetting Model', function (): void {
    it('auto-creates user setting when accessing setting attribute', function (): void {
        $user = NoerdUser::factory()->create();

        expect($user->setting)->toBeInstanceOf(UserSetting::class);
        expect($user->setting->user_id)->toBe($user->id);
        expect($user->setting->locale)->toBe('en');
    });

    it('returns existing user setting when accessing setting attribute', function (): void {
        $user = NoerdUser::factory()->create();
        $setting = UserSetting::factory()->create([
            'user_id' => $user->id,
            'locale' => 'de',
        ]);

        // Refresh user to clear any cached relations
        $user->refresh();

        expect($user->setting->id)->toBe($setting->id);
        expect($user->setting->locale)->toBe('de');
    });

    it('allows setting locale via user attribute', function (): void {
        $user = NoerdUser::factory()->create();

        $user->locale = 'de';

        expect($user->locale)->toBe('de');
        expect($user->setting->fresh()->locale)->toBe('de');
    });

    it('deletes user setting when user is deleted', function (): void {
        $user = NoerdUser::factory()->create();
        $settingId = $user->setting->id;

        $user->delete();

        expect(UserSetting::find($settingId))->toBeNull();
    });

});

describe('TenantSessionHelper', function (): void {
    it('round-trips the selected tenant and app through the session', function (): void {
        TenantHelper::clear();

        expect(TenantHelper::hasTenant())->toBeFalse()
            ->and(TenantHelper::hasApp())->toBeFalse()
            ->and(TenantHelper::getSelectedTenant())->toBeNull()
            ->and(TenantHelper::getSelectedTenantId())->toBeNull();

        $tenant = Tenant::factory()->create();
        TenantHelper::setSelectedTenantId($tenant->id);
        TenantHelper::setSelectedApp('SETUP');

        expect(TenantHelper::getSelectedTenantId())->toBe($tenant->id)
            ->and(session('noerd.selected_tenant_id'))->toBe($tenant->id)
            ->and(TenantHelper::getSelectedApp())->toBe('SETUP')
            ->and(session('noerd.selected_app'))->toBe('SETUP')
            ->and(TenantHelper::hasTenant())->toBeTrue()
            ->and(TenantHelper::hasApp())->toBeTrue();

        TenantHelper::clear();

        expect(TenantHelper::getSelectedTenantId())->toBeNull()
            ->and(TenantHelper::getSelectedApp())->toBeNull();
    });

    it('persists selected_tenant_id to database when user is authenticated', function (): void {
        $user = NoerdUser::factory()->create();
        $tenant = Tenant::factory()->create();

        $this->actingAs($user);

        TenantHelper::setSelectedTenantId($tenant->id);

        expect($user->setting->fresh()->selected_tenant_id)->toBe($tenant->id);
    });

    it('returns selected tenant model', function (): void {
        $tenant = Tenant::factory()->create();
        TenantHelper::setSelectedTenantId($tenant->id);

        $selectedTenant = TenantHelper::getSelectedTenant();

        expect($selectedTenant)->toBeInstanceOf(Tenant::class);
        expect($selectedTenant->id)->toBe($tenant->id);
    });

});

describe('User Model with TenantSessionHelper', function (): void {
    it('persists selected_tenant_id of another user without touching the session', function (): void {
        $admin = NoerdUser::factory()->create();
        $adminTenant = Tenant::factory()->create();
        $this->actingAs($admin);
        TenantHelper::setSelectedTenantId($adminTenant->id);

        $user = NoerdUser::factory()->create();
        $tenant = Tenant::factory()->create();

        $user->selected_tenant_id = $tenant->id;

        expect($user->selected_tenant_id)->toBe($tenant->id);
        expect($user->fresh()->userSetting->selected_tenant_id)->toBe($tenant->id);
        expect(TenantHelper::getSelectedTenantId())->toBe($adminTenant->id);
    });

    it('treats the tenant session as its own while nobody is authenticated', function (): void {
        $user = NoerdUser::factory()->create();
        $tenant = Tenant::factory()->create();

        $user->selected_tenant_id = $tenant->id;

        expect(TenantHelper::getSelectedTenantId())->toBe($tenant->id)
            ->and($user->fresh()->userSetting->selected_tenant_id)->toBe($tenant->id);
    });

    it('syncs the session when the authenticated user selects a tenant via the attribute', function (): void {
        $user = NoerdUser::factory()->create();
        $tenant = Tenant::factory()->create();
        $this->actingAs($user);

        $user->selected_tenant_id = $tenant->id;

        expect($user->selected_tenant_id)->toBe($tenant->id);
        expect(TenantHelper::getSelectedTenantId())->toBe($tenant->id);
        expect($user->fresh()->userSetting->selected_tenant_id)->toBe($tenant->id);
    });

    it('persists a selection assigned before the first save', function (): void {
        $tenant = Tenant::factory()->create();

        $user = NoerdUser::factory()->create(['selected_tenant_id' => $tenant->id]);

        expect($user->fresh()->userSetting->selected_tenant_id)->toBe($tenant->id);
    });

    it('returns selectedTenant from session via User model', function (): void {
        $user = NoerdUser::factory()->create();
        $tenant = Tenant::factory()->create();
        TenantHelper::setSelectedTenantId($tenant->id);

        $selectedTenant = $user->selectedTenant();

        expect($selectedTenant)->toBeInstanceOf(Tenant::class);
        expect($selectedTenant->id)->toBe($tenant->id);
    });
});

describe('InitializeTenantSession', function (): void {
    it('restores saved tenant from database on login', function (): void {
        $user = NoerdUser::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant->id);

        // Save tenant preference in DB
        $user->setting->update(['selected_tenant_id' => $tenant->id]);

        // Ensure no session tenant is set
        TenantHelper::clear();

        // Simulate login
        $listener = new InitializeTenantSession();
        $listener->handle(new Login(NoerdAuth::guardName(), $user, false));

        expect(TenantHelper::getSelectedTenantId())->toBe($tenant->id);
    });

    it('falls back to first tenant when saved tenant is not available', function (): void {
        $user = NoerdUser::factory()->create();
        $tenant = Tenant::factory()->create();
        $inaccessibleTenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant->id);

        // Save a tenant ID the user doesn't have access to
        $user->setting->update(['selected_tenant_id' => $inaccessibleTenant->id]);

        TenantHelper::clear();

        $listener = new InitializeTenantSession();
        $listener->handle(new Login(NoerdAuth::guardName(), $user, false));

        expect(TenantHelper::getSelectedTenantId())->toBe($tenant->id);
    });

    it('falls back to first tenant when no saved tenant exists', function (): void {
        $user = NoerdUser::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant->id);

        TenantHelper::clear();

        $listener = new InitializeTenantSession();
        $listener->handle(new Login(NoerdAuth::guardName(), $user, false));

        expect(TenantHelper::getSelectedTenantId())->toBe($tenant->id);
    });

    it('does not override existing session tenant', function (): void {
        $user = NoerdUser::factory()->create();
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        $user->tenants()->attach([$tenant1->id, $tenant2->id]);

        // Set a session tenant
        TenantHelper::setSelectedTenantId($tenant1->id);

        // Save a different tenant in DB
        $user->setting->update(['selected_tenant_id' => $tenant2->id]);

        $listener = new InitializeTenantSession();
        $listener->handle(new Login(NoerdAuth::guardName(), $user, false));

        // Session tenant should remain unchanged
        expect(TenantHelper::getSelectedTenantId())->toBe($tenant1->id);
    });
});
