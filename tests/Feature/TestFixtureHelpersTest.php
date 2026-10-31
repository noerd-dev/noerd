<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Noerd\Enums\Profile;
use Noerd\Events\TenantAppAssigned;
use Noerd\Helpers\AccessHelper;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\TenantApp;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 | The shipped fixture helpers of tests/helpers.php — the tenant_apps row in
 | its canonical shape, the standard module user and its authenticated
 | variant. Proven against a made-up app key, never a shipped module.
 */

it('creates the tenant app of a module key once, in the canonical shape', function (): void {
    $app = ensureTenantApp('zz-fixture');

    expect($app->name)->toBe('ZZ-FIXTURE')
        ->and($app->title)->toBe('Zz Fixture')
        ->and($app->route)->toBe('zz-fixture')
        ->and($app->is_active)->toBeTrue()
        ->and(ensureTenantApp('ZZ-FIXTURE', ['title' => 'Ignored'])->is($app))->toBeTrue()
        ->and(TenantApp::query()->where('name', 'ZZ-FIXTURE')->count())->toBe(1);
});

it('takes attributes for a new tenant app row', function (): void {
    $app = ensureTenantApp('zz-routed', ['route' => 'zz.dashboard', 'title' => 'Zz Routed App']);

    expect($app->route)->toBe('zz.dashboard')
        ->and($app->title)->toBe('Zz Routed App');
});

it('creates a user on a fresh tenant that runs the app, with tenant and app selected', function (): void {
    Event::fake([TenantAppAssigned::class]);

    $user = createNoerdUserForApp('zz-fixture', ['name' => 'Zz Tenant'], Profile::ReadOnly);
    $tenant = $user->tenants()->firstOrFail();

    expect($tenant->name)->toBe('Zz Tenant')
        ->and($tenant->pivot->profile_key)->toBe(Profile::ReadOnly->value)
        ->and($user->fresh()->selected_tenant_id)->toBe($tenant->id)
        ->and($tenant->tenantApps()->where('name', 'ZZ-FIXTURE')->exists())->toBeTrue()
        ->and(TenantHelper::getSelectedTenantId())->toBe($tenant->id)
        ->and(TenantHelper::getSelectedApp())->toBe('ZZ-FIXTURE')
        ->and(auth(NoerdAuth::GUARD)->check())->toBeFalse();

    // The assignment goes through Tenant::assignApp(), so the listeners of the
    // installed modules see the fixture exactly like an installation's assignment.
    Event::assertDispatched(
        TenantAppAssigned::class,
        fn(TenantAppAssigned $event): bool => $event->tenantId === $tenant->id && $event->appName === 'ZZ-FIXTURE',
    );
});

it('attaches the user under a module profile key or without a profile', function (): void {
    $withKey = createNoerdUserForApp('zz-fixture', [], 'ZZ_MODULE_PROFILE');
    $without = createNoerdUserForApp('zz-fixture');

    expect(DB::table('users_tenants')->where('user_id', $withKey->id)->value('profile_key'))->toBe('ZZ_MODULE_PROFILE')
        ->and(DB::table('users_tenants')->where('user_id', $without->id)->value('profile_key'))->toBeNull()
        ->and($withKey->tenants()->first()?->is($without->tenants()->first()))->toBeFalse()
        ->and(TenantApp::query()->where('name', 'ZZ-FIXTURE')->count())->toBe(1);
});

it('authenticates the user on the noerd guard and lets the tenant use the app', function (): void {
    $user = actingAsNoerdUserForApp('zz-fixture');

    expect(auth(NoerdAuth::GUARD)->id())->toBe($user->id)
        ->and(auth()->id())->toBe($user->id)
        ->and(NoerdAuth::user()?->is($user))->toBeTrue()
        ->and(AccessHelper::canUseApp('ZZ-FIXTURE'))->toBeTrue()
        ->and(AccessHelper::canUseApp('ZZ-ELSEWHERE'))->toBeFalse();
});
