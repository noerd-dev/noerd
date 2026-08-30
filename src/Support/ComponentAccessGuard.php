<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Support\Str;
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
        'noerd::noerd-user-page',
        'noerd::noerd-user-detail',
        'noerd::tenants-list',
        'noerd::tenant-detail',
        'noerd::create-tenant',
        // The inner worker of create-tenant: it holds the actual tenant
        // creation (incl. attaching the caller to the new ADMIN profile), so it
        // must be as unreachable as its wrapper.
        'noerd::create-new-tenant',
        // Sets a user's password; only ever embedded in the user editor.
        'noerd::user-update-password',
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
     *
     * Matching ignores the namespace prefix: noerd registers its components with
     * BOTH a namespace and a bare location (Livewire::addLocation), so
     * `noerd::tenants-list` and `tenants-list` mount the very same admin screen —
     * comparing the full name alone let the bare alias walk straight past this
     * guard. Consequence to be aware of: a host or module component whose bare
     * name matches one of these becomes admin-only too. That is the deliberate
     * fail-closed choice — such a component already collides with noerd's own
     * registration.
     */
    public static function allows(?string $componentName): bool
    {
        if ($componentName === null || $componentName === '') {
            return true;
        }

        $restricted = array_map(
            static fn(string $name): string => self::normalize($name),
            array_merge(self::ADMIN_COMPONENTS, self::$registered),
        );

        if (in_array(self::normalize($componentName), $restricted, true)) {
            return (bool) NoerdAuth::user()?->isAdmin();
        }

        return true;
    }

    /**
     * The comparable identity of a component name — it must collapse EVERY
     * spelling Livewire resolves to the same component file, or the guard is
     * bypassable by writing the name differently.
     *
     * Livewire's Finder strips the ⚡ marker and rewrites '/' to '.'
     * (Finder::normalizeName), then builds the view path from the dot segments,
     * where empty segments simply vanish — so 'x', '.x', '..x' and '/x' all
     * load the same component. The namespace is dropped as well, because noerd
     * registers its components both namespaced and bare (Livewire::addLocation).
     */
    private static function normalize(string $componentName): string
    {
        $name = Str::afterLast($componentName, '::');

        // Mirror Finder::normalizeName(): drop the ⚡ marker (with either
        // variation selector) and treat slashes as dot separators.
        $name = preg_replace('/\x{26A1}[\x{FE0E}\x{FE0F}]?/u', '', $name) ?? $name;
        $name = str_replace(['/', '\\'], '.', $name);

        // Empty segments carry no meaning for the resolver — dropping them is
        // what makes '.tenants-list' and 'tenants-list' compare equal.
        $segments = array_values(array_filter(
            array_map(static fn(string $segment): string => mb_trim($segment), explode('.', $name)),
            static fn(string $segment): bool => $segment !== '',
        ));

        return mb_strtolower(implode('.', $segments));
    }
}
