<?php

declare(strict_types=1);

namespace Noerd\Helpers;

use Illuminate\Support\Facades\Gate;
use Noerd\Enums\Profile;
use Noerd\Models\Tenant;

/**
 * Authorization checks for the generic noerd chrome, layered in two stages:
 *
 * 1. GATES (optional): a project may define the gates below to decide app
 *    access, per-object read/write/create/delete and named action checks
 *    across every list and detail at once. A defined gate decides ALONE — it
 *    is expected to incorporate the profile baseline itself.
 * 2. PROFILE BASELINE (fallback): with no gate defined, the user's per-tenant
 *    profile (users_tenants.profile_key) decides. Admin/User — and users
 *    without a profile — may do everything, ReadOnly may only read (and open
 *    apps). The core ships no finer restrictions of its own.
 *
 * Gate closures MUST accept a nullable user (`?Authenticatable $user`):
 * some call sites (config discovery, unauthenticated rendering) run without a
 * user, and a non-nullable closure would silently deny them. Guests are never restricted
 * by the profile baseline either. The gate user is resolved from noerd's own
 * auth guard (see NoerdAuth), never from the host application's default guard.
 */
final class AccessHelper
{
    public const APP_GATE = 'noerd.access-app';

    public const OBJECT_READ_GATE = 'noerd.object-read';

    public const OBJECT_WRITE_GATE = 'noerd.object-write';

    public const OBJECT_CREATE_GATE = 'noerd.object-create';

    public const OBJECT_DELETE_GATE = 'noerd.object-delete';

    public const ACTION_GATE = 'noerd.action';

    /**
     * @param  string|null  $appName  tenant_apps.name in any case; null/'' (no app known) is allowed
     */
    public static function canAccessApp(?string $appName): bool
    {
        if ($appName === null || $appName === '') {
            return true;
        }

        if (Gate::has(self::APP_GATE)) {
            return Gate::forUser(NoerdAuth::user())->allows(self::APP_GATE, $appName);
        }

        return self::profileBaselineAllows(reading: true);
    }

    /**
     * Whether the current user may USE one of the given apps: the app must be
     * ASSIGNED to the selected tenant AND the app permission must allow it.
     * This is the check for tenant-scoped, app-bound chrome (quick-menu
     * buttons, dashboard widgets — their `app:`/`apps:` YAML keys) and the
     * in-component replacement for the removed per-module tenant gates
     * (canOrders & Co.). Without a selected tenant nothing is assigned, so
     * the answer is false — unlike canAccessApp(), which serves guest-capable
     * call sites and stays permissive.
     *
     * @param  string  ...$appNames  tenant_apps.name in any case; ONE usable app suffices
     */
    public static function canUseApp(string ...$appNames): bool
    {
        $assigned = TenantHelper::getSelectedTenant()?->tenantApps;

        if ($assigned === null) {
            return false;
        }

        $assignedNames = $assigned
            ->pluck('name')
            ->map(fn($name): string => mb_strtoupper((string) $name))
            ->flip();

        foreach ($appNames as $appName) {
            if (isset($assignedNames[mb_strtoupper($appName)]) && self::canAccessApp($appName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  class-string|null  $modelClass  null (no model known) is allowed
     */
    public static function canReadObject(?string $modelClass): bool
    {
        return self::check(self::OBJECT_READ_GATE, $modelClass, reading: true);
    }

    /**
     * @param  class-string|null  $modelClass  null (no model known) is allowed
     */
    public static function canWriteObject(?string $modelClass): bool
    {
        return self::check(self::OBJECT_WRITE_GATE, $modelClass, reading: false);
    }

    /**
     * @param  class-string|null  $modelClass  null (no model known) is allowed
     */
    public static function canCreateObject(?string $modelClass): bool
    {
        return self::check(self::OBJECT_CREATE_GATE, $modelClass, reading: false);
    }

    /**
     * @param  class-string|null  $modelClass  null (no model known) is allowed
     */
    public static function canDeleteObject(?string $modelClass): bool
    {
        return self::check(self::OBJECT_DELETE_GATE, $modelClass, reading: false);
    }

    /**
     * Named action permission (e.g. 'production_start_run'), declared in code
     * via the ActionPermissionRegistry and checked at its call sites (the
     * `action-permission:{key}` middleware or a manual call). Actions are
     * mutations: the profile baseline treats them like writes.
     *
     * @param  string|null  $actionKey  null/'' (no action known) is allowed
     */
    /**
     * A host-defined gate or policy ability (YAML `policy:` key of quick-menu
     * buttons and dashboard widgets): a `Gate::define()`d ability is checked as
     * such, anything else against the Tenant policy.
     */
    public static function canPassGate(string $ability): bool
    {
        $user = NoerdAuth::user();

        if ($user === null) {
            return false;
        }

        return Gate::has($ability) ? $user->can($ability) : $user->can($ability, Tenant::class);
    }

    public static function canPerformAction(?string $actionKey): bool
    {
        if ($actionKey === null || $actionKey === '') {
            return true;
        }

        if (Gate::has(self::ACTION_GATE)) {
            return Gate::forUser(NoerdAuth::user())->allows(self::ACTION_GATE, $actionKey);
        }

        return self::profileBaselineAllows(reading: false);
    }

    private static function check(string $gate, ?string $modelClass, bool $reading): bool
    {
        if ($modelClass === null) {
            return true;
        }

        if (Gate::has($gate)) {
            return Gate::forUser(NoerdAuth::user())->allows($gate, $modelClass);
        }

        return self::profileBaselineAllows($reading);
    }

    /**
     * The core's built-in baseline, applied only while no gate is defined.
     * Unknown profile keys (e.g. module-registered profiles, whose semantics
     * live in that module's gates) and users without a profile behave like
     * User — a missing assignment must never lock an installation out.
     */
    private static function profileBaselineAllows(bool $reading): bool
    {
        $user = NoerdAuth::user();

        if ($user === null || ! method_exists($user, 'currentProfile')) {
            return true;
        }

        // Admins bypass the baseline regardless of any (mis)assigned profile —
        // the same shortcut every gate implementation is expected to apply.
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        return match ($user->currentProfile()) {
            Profile::ReadOnly => $reading,
            default => true,
        };
    }
}
