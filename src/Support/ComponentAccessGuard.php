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
        'noerd::create-tenant-form',
        // Sets a user's password; only ever embedded in the user editor.
        'noerd::user-update-password',
        'noerd::tenant-apps-page',
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
     * Drop every module-contributed entry. The allow-list is process-global and
     * survives an application boot, so a test process that boots several
     * applications has to reset it — the providers of the new app register
     * their own screens again.
     */
    public static function flushRegisteredComponents(): void
    {
        self::$registered = [];
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
     * Matching is namespace-aware, with ONE deliberate exception: a name written
     * WITHOUT a namespace matches an admin entry by its bare name alone. That
     * exception is what keeps the guard closed for noerd's own screens, which
     * are registered both namespaced and bare (Livewire::addLocation), so
     * `noerd::tenants-list` and `tenants-list` mount the very same component.
     * The namespace of a namespaced name is never dropped, though: modules
     * register their screens under their own namespace only, so `cms::settings-page`
     * being admin-only must not lock down `hr::settings-page` as well.
     */
    public static function allows(?string $componentName): bool
    {
        if ($componentName === null || $componentName === '') {
            return true;
        }

        $candidateNamespace = self::namespaceOf($componentName);
        $candidateName = self::normalizeName($componentName);

        foreach (array_merge(self::ADMIN_COMPONENTS, self::$registered) as $restricted) {
            if (self::normalizeName($restricted) !== $candidateName) {
                continue;
            }

            if ($candidateNamespace === null || $candidateNamespace === self::namespaceOf($restricted)) {
                return (bool) NoerdAuth::user()?->isAdmin();
            }
        }

        return true;
    }

    /**
     * The Livewire namespace of a component name, or null when it carries none
     * (a bare component location). Lowercased so `NOERD::x` and `noerd::x`
     * compare equal; an empty prefix (`::x`) counts as no namespace, which
     * matches bare entries and therefore stays fail-closed.
     */
    private static function namespaceOf(string $componentName): ?string
    {
        $name = self::stripMarker($componentName);

        if (! str_contains($name, '::')) {
            return null;
        }

        $namespace = mb_trim(Str::beforeLast($name, '::'));

        return $namespace === '' ? null : mb_strtolower($namespace);
    }

    /**
     * The comparable identity of the name behind the namespace — it must collapse
     * EVERY spelling Livewire resolves to the same component file, or the guard is
     * bypassable by writing the name differently.
     *
     * Livewire's Finder strips the ⚡ marker and rewrites '/' to '.'
     * (Finder::normalizeName), then builds the view path from the dot segments,
     * where empty segments simply vanish — so 'x', '.x', '..x' and '/x' all
     * load the same component.
     */
    private static function normalizeName(string $componentName): string
    {
        $name = Str::afterLast(self::stripMarker($componentName), '::');

        // Mirror Finder::normalizeName(): treat slashes as dot separators.
        $name = str_replace(['/', '\\'], '.', $name);

        // Empty segments carry no meaning for the resolver — dropping them is
        // what makes '.tenants-list' and 'tenants-list' compare equal.
        $segments = array_values(array_filter(
            array_map(static fn(string $segment): string => mb_trim($segment), explode('.', $name)),
            static fn(string $segment): bool => $segment !== '',
        ));

        return mb_strtolower(implode('.', $segments));
    }

    /**
     * Drop the ⚡ marker (with either variation selector) Livewire allows in
     * front of a component name.
     */
    private static function stripMarker(string $componentName): string
    {
        return preg_replace('/\x{26A1}[\x{FE0E}\x{FE0F}]?/u', '', $componentName) ?? $componentName;
    }
}
