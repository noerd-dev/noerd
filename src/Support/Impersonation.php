<?php

declare(strict_types=1);

namespace Noerd\Support;

use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;

/**
 * Signing in as another account ("Login as user") and back again — the single
 * owner of the rule who may impersonate whom and of the session key that
 * remembers the original account, read by the banner, the logout and the
 * login log.
 */
final class Impersonation
{
    private const SESSION_KEY = 'impersonating_from';

    public static function allows(NoerdUser $impersonator, NoerdUser $target): bool
    {
        // Impersonation never nests: a second hop would overwrite the origin
        // and strand the original admin in the impersonated account.
        if (self::active() || ! $impersonator->isAdmin()) {
            return false;
        }

        // A super admin is never impersonatable: attaching such an account to
        // one's own tenant would otherwise be a direct path to a full takeover.
        if ($target->isSuperAdmin()) {
            return false;
        }

        // A super admin administers the installation, so every account it can
        // see is impersonatable; a tenant admin stays confined to the members
        // of the tenants it administers.
        if ($impersonator->isSuperAdmin()) {
            return true;
        }

        return $target->tenants()
            ->whereKey($impersonator->adminTenants()->pluck('tenants.id'))
            ->exists();
    }

    public static function start(NoerdUser $target): void
    {
        $impersonator = NoerdAuth::user();

        abort_unless($impersonator instanceof NoerdUser && self::allows($impersonator, $target), 403);

        session([self::SESSION_KEY => $impersonator->getKey()]);

        self::signIn($target->getKey());
    }

    public static function stop(): void
    {
        $impersonatorId = self::impersonatorId();

        abort_if($impersonatorId === null, 403);

        self::forget();

        self::signIn($impersonatorId);
    }

    public static function active(): bool
    {
        return session()->has(self::SESSION_KEY);
    }

    public static function impersonatorId(): ?int
    {
        $impersonatorId = session(self::SESSION_KEY);

        return $impersonatorId === null ? null : (int) $impersonatorId;
    }

    public static function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    private static function signIn(int $userId): void
    {
        // Clear the tenant session, so InitializeTenantSession selects the
        // tenant of the account that is signing in.
        TenantHelper::clear();

        NoerdAuth::guard()->loginUsingId($userId);
    }
}
