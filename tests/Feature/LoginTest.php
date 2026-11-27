<?php

declare(strict_types=1);

use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Noerd\Enums\Profile;
use Noerd\Listeners\RecordLogin;
use Noerd\Models\NoerdLogin;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Drive the login component the way the browser does: fill both fields and
 * submit. Every case below goes through this single seam.
 */
function zzSubmitLogin(NoerdUser $user, string $password = 'password', bool $remember = false)
{
    return Livewire::test('noerd::auth.login')
        ->set('login', $user->email)
        ->set('password', $password)
        ->set('remember', $remember)
        ->call('authenticate');
}

describe('login flow', function (): void {
    it('redirects to the originally intended URL after login', function (): void {
        $user = NoerdUser::factory()->create([
            'password' => bcrypt('password'),
        ]);

        session()->put('url.intended', '/restaurant');

        zzSubmitLogin($user)->assertRedirect('/restaurant');
    });

    it('fails login with invalid credentials', function (): void {
        $user = NoerdUser::factory()->create([
            'password' => bcrypt('password'),
        ]);

        zzSubmitLogin($user, 'wrong-password')->assertHasErrors(['login']);
    });

    it('requires email and password', function (): void {
        Livewire::test('noerd::auth.login')
            ->call('authenticate')
            ->assertHasErrors(['login', 'password']);
    });

    it('rate limits login attempts', function (): void {
        $user = NoerdUser::factory()->create([
            'password' => bcrypt('password'),
        ]);

        $component = Livewire::test('noerd::auth.login')
            ->set('login', $user->email);

        for ($i = 0; $i < 5; $i++) {
            $component->set('password', 'wrong-password')
                ->call('authenticate');
        }

        $component->set('password', 'wrong-password')
            ->call('authenticate')
            ->assertHasErrors(['login']);
    });
});

describe('landing page', function (): void {
    /** A tenant with the given apps (name => route), each route registered. */
    function zzTenantWithApps(array $apps): Tenant
    {
        $tenant = Tenant::factory()->create();

        foreach ($apps as $name => $route) {
            Route::get("/{$route}", fn() => 'ok')->name($route);
            $tenant->assignApp(TenantApp::create([
                'title' => $name,
                'name' => $name,
                'icon' => 'noerd::icons.app',
                'route' => $route,
                'is_active' => true,
            ]));
        }

        return $tenant;
    }

    it('takes a person who can open exactly one app straight to it', function (): void {
        $tenant = zzTenantWithApps(['ZZ_ONLY' => 'zz-only']);
        $user = NoerdUser::factory()->create(['password' => bcrypt('password')]);
        $tenant->addMember($user, Profile::User->value);

        zzSubmitLogin($user)->assertRedirect('/zz-only');
    });

    it('keeps the home for several apps and for an administrator', function (): void {
        $several = zzTenantWithApps(['ZZ_ONE' => 'zz-one', 'ZZ_TWO' => 'zz-two']);
        $user = NoerdUser::factory()->create(['password' => bcrypt('password')]);
        $several->addMember($user, Profile::User->value);

        zzSubmitLogin($user)->assertRedirect(route('noerd.apps', absolute: false));

        auth('noerd')->logout();

        $single = zzTenantWithApps(['ZZ_ADMIN_ONLY' => 'zz-admin-only']);
        $admin = NoerdUser::factory()->create(['password' => bcrypt('password')]);
        $single->addMember($admin, Profile::Admin->value);

        zzSubmitLogin($admin)->assertRedirect(route('noerd.apps', absolute: false));
    });
});

describe('sign-in name', function (): void {
    beforeEach(fn() => config(['noerd.features.username_login' => true]));

    it('takes an e-mail address only while usernames are switched off', function (): void {
        config(['noerd.features.username_login' => false]);

        NoerdUser::factory()->create(['username' => 'm.muster', 'password' => bcrypt('password')]);

        Livewire::test('noerd::auth.login')
            ->assertSee(__('Email address'))
            ->assertDontSee(__('Email address or username'))
            ->set('login', 'm.muster')
            ->set('password', 'password')
            ->call('authenticate')
            ->assertHasErrors(['login']);

        expect(auth('noerd')->check())->toBeFalse()
            ->and(NoerdUser::findForLogin('m.muster'))->toBeNull();
    });

    it('signs in with the username', function (): void {
        $user = NoerdUser::factory()->create(['username' => 'm.muster', 'password' => bcrypt('password')]);

        Livewire::test('noerd::auth.login')
            ->set('login', ' M.Muster ')
            ->set('password', 'password')
            ->call('authenticate')
            ->assertHasNoErrors();

        expect(auth('noerd')->id())->toBe($user->id);
    });

    it('signs in an account without an e-mail address', function (): void {
        $user = NoerdUser::factory()->create(['email' => null, 'username' => 'lager7', 'password' => bcrypt('password')]);

        Livewire::test('noerd::auth.login')
            ->set('login', 'lager7')
            ->set('password', 'password')
            ->call('authenticate')
            ->assertHasNoErrors();

        expect(auth('noerd')->id())->toBe($user->id)
            ->and($user->loginName())->toBe('lager7')
            ->and($user->initials())->not->toBe('');
    });

    it('finds the e-mail address whatever its case', function (): void {
        $user = NoerdUser::factory()->create(['email' => 'Jane.Doe@example.com']);

        expect(NoerdUser::findForLogin('jane.doe@EXAMPLE.com')?->id)->toBe($user->id)
            ->and(NoerdUser::findForLogin('jane.doe'))->toBeNull()
            ->and(NoerdUser::findForLogin(''))->toBeNull();
    });

    it('refuses an account without any sign-in name and a username that looks like an address', function (): void {
        expect(fn() => NoerdUser::factory()->create(['email' => null, 'username' => null]))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn() => NoerdUser::factory()->create(['username' => 'jane@example.com']))
            ->toThrow(InvalidArgumentException::class);
    });
});

describe('login recording', function (): void {
    it('persists a login row for every successful login', function (): void {
        $user = NoerdUser::factory()->create(['password' => bcrypt('password')]);

        zzSubmitLogin($user);
        zzSubmitLogin($user);

        expect(NoerdLogin::where('user_id', $user->id)->count())->toBe(2);

        $login = NoerdLogin::where('user_id', $user->id)->latest('id')->first();
        expect($login->impersonated_by_id)->toBeNull();
        expect($login->created_at)->not->toBeNull();
    });

    it('records nothing for a failed login', function (): void {
        $user = NoerdUser::factory()->create(['password' => bcrypt('password')]);

        zzSubmitLogin($user, 'wrong-password')->assertHasErrors(['login']);

        expect(NoerdLogin::count())->toBe(0);
    });

    it('stores the remember flag and the request metadata', function (): void {
        $user = NoerdUser::factory()->create(['password' => bcrypt('password')]);

        zzSubmitLogin($user, remember: true);

        $login = NoerdLogin::where('user_id', $user->id)->sole();

        expect($login->remember)->toBeTrue();
        expect($login->ip_address)->not->toBeNull();
    });

    it('marks an impersonated login with the impersonating user', function (): void {
        $admin = NoerdUser::factory()->create();
        $user = NoerdUser::factory()->create();

        session(['impersonating_from' => $admin->id]);

        (new RecordLogin())->handle(new Login('noerd', $user, false));

        $login = NoerdLogin::where('user_id', $user->id)->sole();

        expect($login->impersonated_by_id)->toBe($admin->id);
        expect($login->impersonatedBy->is($admin))->toBeTrue();
    });

    it('ignores logins on a foreign guard', function (): void {
        $user = NoerdUser::factory()->create();

        (new RecordLogin())->handle(new Login('web', $user, false));

        expect(NoerdLogin::count())->toBe(0);
    });

    it('removes the login rows when the user is deleted', function (): void {
        $user = NoerdUser::factory()->create();
        NoerdLogin::factory()->count(3)->create(['user_id' => $user->id]);

        $user->delete();

        expect(NoerdLogin::count())->toBe(0);
    });

    it('exposes the most recent login through the latestLogin relation', function (): void {
        $user = NoerdUser::factory()->create();

        NoerdLogin::create(['user_id' => $user->id, 'created_at' => now()->subDays(3)]);
        $latest = NoerdLogin::create(['user_id' => $user->id, 'created_at' => now()->subHour()]);

        expect($user->latestLogin()->first()->id)->toBe($latest->id);
    });

    it('eager-loads the last login so a list column resolves without a per-row query', function (): void {
        $user = NoerdUser::factory()->create();
        NoerdLogin::create(['user_id' => $user->id, 'created_at' => now()->subHour()]);

        $loaded = NoerdUser::with('latestLogin')->whereKey($user->id)->sole();

        expect($loaded->relationLoaded('latestLogin'))->toBeTrue();
        expect(data_get($loaded, 'latestLogin.created_at'))->not->toBeNull();
    });

    it('leaves the last login empty for a user who never logged in', function (): void {
        $user = NoerdUser::factory()->create();

        expect($user->latestLogin()->first())->toBeNull();
    });
});

it('submits the form to an action that is no public property of the component', function (): void {
    // In the browser `$wire.{name}` answers a property before a method: a
    // form submitting to the name of a property sends no request at all.
    $component = Livewire::test('noerd::auth.login');

    preg_match('/wire:submit="([^"]+)"/', $component->html(), $matches);

    expect($matches[1] ?? null)->not->toBeNull()
        ->and(method_exists($component->instance(), $matches[1]))->toBeTrue()
        ->and(property_exists($component->instance(), $matches[1]))->toBeFalse();
});
