<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
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

    it('allows setting selected_tenant_id via user attribute', function (): void {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $user->selected_tenant_id = $tenant->id;

        expect($user->selected_tenant_id)->toBe($tenant->id);
        expect($user->setting->fresh()->selected_tenant_id)->toBe($tenant->id);
    });

    it('allows setting selected_app via user attribute', function (): void {
        $user = User::factory()->create();

        $user->selected_app = 'setup';

        expect($user->selected_app)->toBe('setup');
        expect($user->setting->fresh()->selected_app)->toBe('setup');
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

    it('has relationship to selected tenant', function (): void {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $user->setting->update(['selected_tenant_id' => $tenant->id]);

        expect($user->setting->selectedTenant->id)->toBe($tenant->id);
    });

    it('has userSetting relationship on User model', function (): void {
        $user = User::factory()->create();

        expect($user->userSetting())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasOne::class);
    });
});

describe('UserSetting via User Factory', function (): void {
    it('creates user with tenant via withExampleTenant', function (): void {
        $user = User::factory()->withExampleTenant()->create();

        expect($user->tenants)->toHaveCount(1);
        expect($user->selected_tenant_id)->toBe($user->tenants->first()->id);
    });

    it('creates admin user via adminUser', function (): void {
        $user = User::factory()->adminUser()->create();

        expect($user->tenants)->toHaveCount(1);
        expect($user->selected_tenant_id)->toBe($user->tenants->first()->id);
        expect($user->isAdmin())->toBeTrue();
    });

    it('sets selected_app via withSelectedApp', function (): void {
        $user = User::factory()->withSelectedApp('setup')->create();

        expect($user->selected_app)->toBe('SETUP');
    });
});
