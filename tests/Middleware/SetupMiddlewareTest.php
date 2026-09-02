<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Noerd\Enums\Profile;
use Noerd\Helpers\TenantHelper;
use Noerd\Middleware\SetupMiddleware;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(TestCase::class, RefreshDatabase::class);

/*
 | SetupMiddleware guards the whole /setup area. Its ORDER matters: a user
 | without any tenant is sent to the no-tenant page before anything is written
 | to the session, and the admin check runs BEFORE the app switch, so a denied
 | user is never left with SETUP selected.
 */

beforeEach(function (): void {
    config()->set('noerd.features.multi_tenant', true);
});

it('forbids a guest', function (): void {
    // The route stack redirects a guest at NoerdAuthenticate, so the middleware's
    // own guest branch is exercised directly.
    $middleware = new SetupMiddleware();

    expect(fn(): mixed => $middleware->handle(Request::create('/setup', 'GET'), fn() => response('OK')))
        ->toThrow(HttpException::class);
});

it('sends an admin without any tenant to the no-tenant page without selecting an app', function (): void {
    $user = NoerdUser::factory()->create();

    $this->actingAs($user)
        ->get(route('noerd.setup'))
        ->assertRedirect(route('noerd.no-tenant'));

    expect(session('noerd.selected_app'))->toBeNull();
});

it('selects the first tenant when none is chosen yet', function (): void {
    $first = Tenant::factory()->create(['name' => 'Zz First']);
    Tenant::factory()->create(['name' => 'Zz Second']);

    $user = NoerdUser::factory()->create();
    $user->tenants()->attach($first->id, ['profile_key' => Profile::Admin->value]);

    $this->actingAs($user)->get(route('noerd.setup'))->assertOk();

    expect(TenantHelper::getSelectedTenantId())->toBe($first->id)
        ->and(session('noerd.selected_app'))->toBe('SETUP');
});

it('forbids a non-admin without touching the selected app', function (): void {
    $tenant = Tenant::factory()->create();
    $user = NoerdUser::factory()->create();
    $user->tenants()->attach($tenant->id, ['profile_key' => Profile::User->value]);

    TenantHelper::setSelectedTenantId($tenant->id);
    TenantHelper::setSelectedApp('ZZAPP');
    $this->actingAs($user);

    $this->get(route('noerd.setup'))->assertForbidden();

    expect(session('noerd.selected_app'))->toBe('ZZAPP');
});
