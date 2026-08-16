<?php

declare(strict_types=1);

namespace Noerd\Helpers;

use Illuminate\Contracts\Auth\Authenticatable;
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
 * stack (Nova, Breeze, ...) that owns the default guard.
 */
final class NoerdAuth
{
    public static function guardName(): string
    {
        return config('noerd.auth.guard', 'noerd');
    }

    public static function guard(): StatefulGuard
    {
        /** @var StatefulGuard */
        return Auth::guard(self::guardName());
    }

    public static function user(): ?Authenticatable
    {
        return self::guard()->user();
    }

    public static function id(): int|string|null
    {
        return self::guard()->id();
    }

    public static function check(): bool
    {
        return self::guard()->check();
    }

    public static function providerName(): string
    {
        return config('noerd.auth.provider', 'noerd_users');
    }

    public static function brokerName(): string
    {
        return config('noerd.auth.passwords', 'noerd_users');
    }

    public static function broker(): PasswordBroker
    {
        return Password::broker(self::brokerName());
    }

    public static function userModel(): string
    {
        return config('noerd.auth.model', NoerdUser::class);
    }
}
