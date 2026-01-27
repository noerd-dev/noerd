<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Noerd\Helpers\TenantHelper;
use Noerd\Noerd\Models\Tenant;
use Noerd\Noerd\Models\User;
use Noerd\Noerd\Models\UserSetting;

uses(Tests\TestCase::class, RefreshDatabase::class);

describe('UserSetting Model', function (): void {
    it('auto-creates user setting when accessing setting attribute', function (): void {
        $user = User::factory()->create();

        expect($user->setting)->toBeInstanceOf(UserSetting::class);
        expect($user->setting->user_id)->toBe($user->id);
        expect($user->setting->locale)->toBe('en');
    });

    it('returns existing user setting when accessing setting attribute', function (): void {
        $user = User::factory()->create();
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
        $user = User::factory()->create();

        $user->locale = 'de';

        expect($user->locale)->toBe('de');
        expect($user->setting->fresh()->locale)->toBe('de');
    });

    it('deletes user setting when user is deleted', function (): void {
        $user = User::factory()->create();
        $settingId = $user->setting->id;

        $user->delete();

        expect(UserSetting::find($settingId))->toBeNull();
    });

    it('has userSetting relationship on User model', function (): void {
        $user = User::factory()->create();

        expect($user->userSetting())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasOne::class);
    });
});

describe('TenantSessionHelper', function (): void {
    it('allows setting selected_tenant_id via session', function (): void {
        $tenant = Tenant::factory()->create();

        TenantHelper::setSelectedTenantId($tenant->id);

        expect(TenantHelper::getSelectedTenantId())->toBe($tenant->id);
        expect(session('noerd.selected_tenant_id'))->toBe($tenant->id);
    });

    it('allows setting selected_app via session', function (): void {
        TenantHelper::setSelectedApp('SETUP');

        expect(TenantHelper::getSelectedApp())->toBe('SETUP');
        expect(session('noerd.selected_app'))->toBe('SETUP');
    });

    it('returns selected tenant model', function (): void {
        $tenant = Tenant::factory()->create();
        TenantHelper::setSelectedTenantId($tenant->id);

        $selectedTenant = TenantHelper::getSelectedTenant();

        expect($selectedTenant)->toBeInstanceOf(Tenant::class);
        expect($selectedTenant->id)->toBe($tenant->id);
    });

    it('returns null when no tenant is selected', function (): void {
        TenantHelper::clear();

        expect(TenantHelper::getSelectedTenant())->toBeNull();
        expect(TenantHelper::getSelectedTenantId())->toBeNull();
    });

    it('can check if tenant is selected', function (): void {
        TenantHelper::clear();
        expect(TenantHelper::hasTenant())->toBeFalse();

        $tenant = Tenant::factory()->create();
        TenantHelper::setSelectedTenantId($tenant->id);
        expect(TenantHelper::hasTenant())->toBeTrue();
    });

    it('can check if app is selected', function (): void {
        TenantHelper::clear();
        expect(TenantHelper::hasApp())->toBeFalse();

        TenantHelper::setSelectedApp('SETUP');
        expect(TenantHelper::hasApp())->toBeTrue();
    });

    it('can clear the session', function (): void {
        $tenant = Tenant::factory()->create();
        TenantHelper::setSelectedTenantId($tenant->id);
        TenantHelper::setSelectedApp('SETUP');

        TenantHelper::clear();

        expect(TenantHelper::getSelectedTenantId())->toBeNull();
        expect(TenantHelper::getSelectedApp())->toBeNull();
    });
});

describe('User Model with TenantSessionHelper', function (): void {
    it('allows setting selected_tenant_id via user attribute using session', function (): void {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $user->selected_tenant_id = $tenant->id;

        expect($user->selected_tenant_id)->toBe($tenant->id);
        expect(TenantHelper::getSelectedTenantId())->toBe($tenant->id);
    });

    it('allows setting selected_app via user attribute using session', function (): void {
        $user = User::factory()->create();

        $user->selected_app = 'SETUP';

        expect($user->selected_app)->toBe('SETUP');
        expect(TenantHelper::getSelectedApp())->toBe('SETUP');
    });

    it('returns selectedTenant from session via User model', function (): void {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        TenantHelper::setSelectedTenantId($tenant->id);

        $selectedTenant = $user->selectedTenant();

        expect($selectedTenant)->toBeInstanceOf(Tenant::class);
        expect($selectedTenant->id)->toBe($tenant->id);
    });
});

describe('UserSetting via User Factory', function (): void {
    it('creates user with tenant via withExampleTenant', function (): void {
        $user = User::factory()->withExampleTenant()->create();

        expect($user->tenants)->toHaveCount(1);
        expect(TenantHelper::getSelectedTenantId())->toBe($user->tenants->first()->id);
    });

    it('creates admin user via adminUser', function (): void {
        $user = User::factory()->adminUser()->create();

        expect($user->tenants)->toHaveCount(1);
        expect(TenantHelper::getSelectedTenantId())->toBe($user->tenants->first()->id);
        expect($user->isAdmin())->toBeTrue();
    });

    it('sets selected_app via withSelectedApp', function (): void {
        $user = User::factory()->withExampleTenant()->withSelectedApp('setup')->create();

        expect(TenantHelper::getSelectedApp())->toBe('SETUP');
    });
});
