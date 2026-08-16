<?php

declare(strict_types=1);

use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Listeners\InitializeTenantSession;
use Noerd\Models\NoerdUser;
use Noerd\Models\SetupLanguage;
use Noerd\Models\Tenant;
use Noerd\Providers\NoerdServiceProvider;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function reRunGuardRegistration(): void
{
    $provider = new NoerdServiceProvider(app());
    (new ReflectionMethod($provider, 'registerNoerdGuard'))->invoke($provider);
}

describe('runtime guard registration', function (): void {
    it('registers guard, provider and password broker with the expected shapes', function (): void {
        expect(config('auth.guards.noerd'))->toBe([
            'driver' => 'session',
            'provider' => 'noerd_users',
        ]);

        expect(config('auth.providers.noerd_users'))->toBe([
            'driver' => 'eloquent',
            'model' => NoerdUser::class,
        ]);

        expect(config('auth.passwords.noerd_users'))->toMatchArray([
            'provider' => 'noerd_users',
            'table' => 'password_reset_tokens',
        ]);
    });

    it('never clobbers a host-defined guard, provider or broker', function (): void {
        $hostGuard = ['driver' => 'session', 'provider' => 'users'];
        $hostProvider = ['driver' => 'eloquent', 'model' => 'App\Models\HostUser'];
        $hostBroker = ['provider' => 'users', 'table' => 'host_reset_tokens', 'expire' => 30, 'throttle' => 30];

        config([
            'auth.guards.noerd' => $hostGuard,
            'auth.providers.noerd_users' => $hostProvider,
            'auth.passwords.noerd_users' => $hostBroker,
        ]);

        reRunGuardRegistration();

        expect(config('auth.guards.noerd'))->toBe($hostGuard);
        expect(config('auth.providers.noerd_users'))->toBe($hostProvider);
        expect(config('auth.passwords.noerd_users'))->toBe($hostBroker);
    });

    it('leaves the default guard untouched when set_as_default is false', function (): void {
        config([
            'noerd.auth.set_as_default' => false,
            'auth.defaults.guard' => 'web',
            'auth.defaults.passwords' => 'users',
        ]);

        reRunGuardRegistration();

        expect(config('auth.defaults.guard'))->toBe('web');
        expect(config('auth.defaults.passwords'))->toBe('users');
    });

    it('takes over the default guard when set_as_default is true', function (): void {
        config([
            'noerd.auth.set_as_default' => true,
            'auth.defaults.guard' => 'web',
            'auth.defaults.passwords' => 'users',
        ]);

        reRunGuardRegistration();

        expect(config('auth.defaults.guard'))->toBe('noerd');
        expect(config('auth.defaults.passwords'))->toBe('noerd_users');
    });
});

describe('login flow', function (): void {
    it('authenticates on the noerd guard and not on a host guard', function (): void {
        $user = NoerdUser::factory()->create(['password' => bcrypt('password')]);

        Livewire::test('noerd::auth.login')
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertRedirect('/noerd-apps');

        expect(Auth::guard('noerd')->check())->toBeTrue();
        expect(Auth::guard('noerd')->id())->toBe($user->id);
        expect(Auth::guard('web')->check())->toBeFalse();
        expect($user->fresh()->last_login_at)->not->toBeNull();
    });

    it('redirects an authenticated noerd user away from the login page', function (): void {
        $user = NoerdUser::factory()->create();
        $this->actingAs($user, 'noerd');

        $this->get('/login')->assertRedirect();
    });
});

describe('tenant scoping via the noerd guard', function (): void {
    it('scopes and stamps by the noerd user even when a host guard is the request default', function (): void {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $noerdUser = NoerdUser::factory()->create();
        $noerdUser->tenants()->attach($tenantB->id);
        $this->actingAs($noerdUser, 'noerd');
        TenantHelper::setSelectedTenantId($tenantB->id);

        // Simulate the coexistence host: a different guard with a different
        // user is the request default (e.g. Nova's web guard).
        $hostUser = NoerdUser::factory()->create();
        Auth::guard('web')->setUser($hostUser);
        config(['auth.defaults.guard' => 'web']);

        $visible = SetupLanguage::query()->get();
        expect($visible)->not->toBeEmpty();
        expect($visible->pluck('tenant_id')->unique()->all())->toBe([$tenantB->id]);

        $created = SetupLanguage::create([
            'code' => 'fr',
            'name' => 'Français',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 99,
        ]);
        expect($created->tenant_id)->toBe($tenantB->id);
    });

    it('applies no tenant scope when no noerd user is authenticated', function (): void {
        Tenant::factory()->create();
        Tenant::factory()->create();

        $tenantIds = SetupLanguage::query()->get()->pluck('tenant_id')->unique();
        expect($tenantIds->count())->toBeGreaterThan(1);
    });
});

describe('logout isolation', function (): void {
    it('logs out only the noerd guard and keeps a host guard session alive', function (): void {
        $tenant = Tenant::factory()->create();
        $noerdUser = NoerdUser::factory()->create();
        $hostUser = NoerdUser::factory()->create();

        $this->actingAs($hostUser, 'web');
        $this->actingAs($noerdUser, 'noerd');
        TenantHelper::setSelectedTenantId($tenant->id);

        Livewire::test('noerd::layout.top-bar')
            ->call('logout')
            ->assertRedirect('/login');

        expect(Auth::guard('noerd')->check())->toBeFalse();
        expect(Auth::guard('web')->check())->toBeTrue();
        expect(session()->has('noerd.selected_tenant_id'))->toBeFalse();
        expect(session()->has('impersonating_from'))->toBeFalse();
    });
});

describe('tenant session listener', function (): void {
    it('ignores Login events fired by other guards', function (): void {
        // A host-guard user has no noerd tenant session and may not even have
        // a setting relation — the listener must not touch it.
        $user = NoerdUser::factory()->create();

        (new InitializeTenantSession())->handle(new Login('web', $user, false));

        expect(TenantHelper::hasTenant())->toBeFalse();
    });

    it('initializes the tenant session for noerd-guard logins', function (): void {
        $tenant = Tenant::factory()->create();
        $user = NoerdUser::factory()->create();
        $user->tenants()->attach($tenant->id);

        (new InitializeTenantSession())->handle(new Login(NoerdAuth::guardName(), $user, false));

        expect(TenantHelper::getSelectedTenantId())->toBe($tenant->id);
    });
});

describe('password broker', function (): void {
    it('creates and validates reset tokens through the noerd broker regardless of the default broker', function (): void {
        config(['auth.defaults.passwords' => 'users']);

        $user = NoerdUser::factory()->create();

        $token = NoerdAuth::broker()->createToken($user);

        expect($token)->toBeString()->not->toBeEmpty();
        expect(NoerdAuth::broker()->tokenExists($user, $token))->toBeTrue();
    });
});
