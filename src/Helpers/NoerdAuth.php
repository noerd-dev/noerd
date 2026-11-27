<?php

declare(strict_types=1);

namespace Noerd\Helpers;

use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Noerd\Models\NoerdUser;

/**
 * Resolves noerd's dedicated auth guard, user provider and password broker.
 *
 * Noerd never relies on the host application's default guard: all framework
 * internals (tenant scoping, middleware, permission resolvers) resolve the
 * current user through this helper, so noerd coexists with any host auth
 * stack (Nova, Breeze, ...) that owns the default guard. The guard, provider
 * and broker names are fixed — a host tunes their definitions (driver, broker
 * table) in config/auth.php, where an existing key always wins over the
 * runtime registration.
 */
final class NoerdAuth
{
    public const GUARD = 'noerd';

    public const PROVIDER = 'noerd_users';

    public const BROKER = 'noerd_users';

    public static function guardName(): string
    {
        return self::GUARD;
    }

    public static function guard(): StatefulGuard
    {
        /** @var StatefulGuard */
        return Auth::guard(self::guardName());
    }

    /**
     * The signed-in account of the noerd guard as a NoerdUser — null for a
     * guest and for anything else a host's guard definition might hand back
     * (a host-tuned provider model that is no NoerdUser), so every caller can
     * rely on the NoerdUser API.
     */
    public static function user(): ?NoerdUser
    {
        $user = self::guard()->user();

        return $user instanceof NoerdUser ? $user : null;
    }

    public static function id(): int|string|null
    {
        return self::guard()->id();
    }

    public static function check(): bool
    {
        return self::guard()->check();
    }

    /**
     * Whether people sign in with e-mail and password. Off
     * (`noerd.features.password_login`), the login page shows only what modules
     * put into the login slot (single sign-on) and every password screen —
     * login form, reset, own and administered password — is refused.
     */
    public static function passwordLoginEnabled(): bool
    {
        return (bool) config('noerd.features.password_login', true);
    }

    /**
     * Whether an account may sign in with a USERNAME instead of its e-mail
     * address (`noerd.features.username_login`, off by default). On, the
     * login field takes either, an account may exist without an address and
     * Setup → Users offers the username. Off, noerd signs in by e-mail only.
     */
    public static function usernameLoginEnabled(): bool
    {
        return (bool) config('noerd.features.username_login', false);
    }

    /**
     * @internal Read by the core provider when it registers the noerd guard.
     */
    public static function providerName(): string
    {
        return self::PROVIDER;
    }

    /**
     * @internal Read by the core provider when it registers the noerd guard.
     */
    public static function brokerName(): string
    {
        return self::BROKER;
    }

    /**
     * @internal Read by the core provider when it registers the noerd guard.
     */
    public static function broker(): PasswordBroker
    {
        return Password::broker(self::brokerName());
    }
}
