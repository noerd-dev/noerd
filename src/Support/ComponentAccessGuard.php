<?php

declare(strict_types=1);

namespace Noerd\Support;

use Noerd\Helpers\NoerdAuth;

/**
 * Authorization guard for dynamic component mounts.
 *
 * Admin/setup screens are protected by route middleware (SetupMiddleware →
 * isAdmin), but a component can also be mounted outside its route — via the
 * client-dispatchable `noerdModal` event or the generic component page — where
 * that middleware never runs. Those entry points call this guard to re-assert
 * admin access before instantiating the component.
 *
 * Modules add their own admin components via registerAdminComponents().
 */
final class ComponentAccessGuard
{
    /**
     * Core screens that require a tenant admin. Kept in lockstep with the
     * `['noerd','setup']` route group in routes/noerd-routes.php.
     *
     * @var array<int, string>
     */
    private const ADMIN_COMPONENTS = [
        'noerd::noerd-users-list',
        'noerd::noerd-user-detail',
        'noerd::tenants-list',
        'noerd::tenant-detail',
        'noerd::create-tenant',
        'noerd::tenant-apps-list',
        'noerd::system-settings-page',
        'noerd::setup-collections-list',
        'noerd::setup-collection-detail',
        'noerd::setup-collection-definitions-list',
        'noerd::setup-collection-definition-detail',
        'noerd::setup-languages-list',
        'noerd::setup-language-detail',
    ];

    /**
     * Admin components contributed by modules (registered in their ServiceProvider).
     *
     * @var array<int, string>
     */
    private static array $registered = [];

    /**
     * Add module-owned admin components to the allow-list. Idempotent.
     *
     * @param  array<int, string>  $componentNames
     */
    public static function registerAdminComponents(array $componentNames): void
    {
        self::$registered = array_values(array_unique(array_merge(self::$registered, $componentNames)));
    }

    /**
     * Abort with 403 when the current user may not mount the given component.
     */
    public static function authorize(?string $componentName): void
    {
        if (! self::allows($componentName)) {
            abort(403);
        }
    }

    /**
     * Whether the current user may mount the given component. Components that are
     * not on the admin allow-list are permitted here — they are guarded by their
     * own route middleware / object gates; this guard only closes the admin
     * bypass at the dynamic-mount seams.
     */
    public static function allows(?string $componentName): bool
    {
        if ($componentName === null || $componentName === '') {
            return true;
        }

        if (in_array($componentName, self::ADMIN_COMPONENTS, true)
            || in_array($componentName, self::$registered, true)) {
            return (bool) NoerdAuth::user()?->isAdmin();
        }

        return true;
    }
}
