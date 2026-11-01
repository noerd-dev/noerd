<?php

declare(strict_types=1);

namespace Noerd\Support;

use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;

/**
 * Signing in as another account ("Login as user") and back again — the single
 * owner of the rule who may impersonate whom and of the session keys that
 * remember the original account and the tenant the impersonation is bound to,
 * read by the banner, the logout, the login log and the tenant access checks.
 *
 * An impersonation is PINNED to the tenant the administrator had selected when
 * starting it: the target must belong to that tenant, and while it lasts the
 * impersonated account can neither enter nor see any other tenant it belongs
 * to (`NoerdUser::accessibleTenants()` / `canAccessTenant()`). Administering
 * one tenant never opens the other tenants of a shared account.
 *
 * @internal
 */
final class Impersonation
{
    private const SESSION_KEY = 'impersonating_from';

    private const TENANT_KEY = 'impersonating_tenant';

    private const TARGET_KEY = 'impersonating_target';

    /**
     * Whether the impersonator may sign in as the target inside the given
     * tenant (default: the tenant selected in the current session).
     */
    public static function allows(NoerdUser $impersonator, NoerdUser $target, ?int $tenantId = null): bool
    {
        // Impersonation never nests: a second hop would overwrite the origin
        // and strand the original admin in the impersonated account.
        if (self::active()) {
            return false;
        }

        $tenantId ??= TenantHelper::getSelectedTenantId();

        // The impersonation is bound to one tenant, which the impersonator
        // must administer — a super admin administers every tenant.
        if ($tenantId === null || ! $impersonator->isAdmin($tenantId)) {
            return false;
        }

        // A super admin is never impersonatable: attaching such an account to
        // one's own tenant would otherwise be a direct path to a full takeover.
        if ($target->isSuperAdmin() || $target->is($impersonator)) {
            return false;
        }

        // Only a member of that tenant — for a super admin as well: the session
        // that follows is pinned to the tenant, so a non-member would land in
        // a tenant it cannot use.
        return $target->tenants()->whereKey($tenantId)->exists();
    }

    public static function start(NoerdUser $target): void
    {
        $impersonator = NoerdAuth::user();
        $tenantId = TenantHelper::getSelectedTenantId();

        abort_unless($impersonator instanceof NoerdUser && self::allows($impersonator, $target, $tenantId), 403);

        session([
            self::SESSION_KEY => $impersonator->getKey(),
            self::TENANT_KEY => $tenantId,
            self::TARGET_KEY => $target->getKey(),
        ]);

        self::signIn((int) $target->getKey(), $tenantId);
    }

    public static function stop(): void
    {
        $impersonatorId = self::impersonatorId();
        $tenantId = self::pinnedTenantId();

        abort_if($impersonatorId === null, 403);

        self::forget();

        self::signIn($impersonatorId, $tenantId);
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

    /**
     * The tenant the running impersonation is bound to.
     */
    public static function pinnedTenantId(): ?int
    {
        $tenantId = session(self::TENANT_KEY);

        return $tenantId === null ? null : (int) $tenantId;
    }

    /**
     * Whether the given account is the one being impersonated in this session.
     * Read from the session rather than the guard: the guard still holds the
     * administrator while the Login event of the switch is dispatched.
     */
    public static function isImpersonated(NoerdUser $user): bool
    {
        return self::active() && (int) session(self::TARGET_KEY) === (int) $user->getKey();
    }

    public static function forget(): void
    {
        session()->forget([self::SESSION_KEY, self::TENANT_KEY, self::TARGET_KEY]);
    }

    private static function signIn(int $userId, ?int $tenantId): void
    {
        // Clear the tenant session (and the selected app), so the account that
        // is signing in starts from a clean state.
        TenantHelper::clear();

        NoerdAuth::guard()->loginUsingId($userId);

        $user = NoerdAuth::user();

        // Continue in the tenant the impersonation is bound to — on the way in
        // the only tenant the target may use, on the way back the tenant the
        // administrator worked in. The login listener's own pick is replaced.
        if ($tenantId !== null && $user instanceof NoerdUser && $user->canAccessTenant($tenantId)) {
            TenantHelper::setSelectedTenantId($tenantId);
        }
    }
}
