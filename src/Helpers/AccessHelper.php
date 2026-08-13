<?php

namespace Noerd\Helpers;

use Illuminate\Support\Facades\Gate;

/**
 * Optional authorization gates for the generic noerd chrome. A project may
 * define these gates (e.g. in a service provider's boot()) to restrict app
 * tiles/routes and per-object read/write/delete across every list and detail
 * at once. An undefined gate allows everything — the core ships no
 * restrictions of its own.
 *
 * Gate closures MUST accept a nullable user (`?Authenticatable $user`):
 * some call sites (public apps, config discovery) run for guests, and a
 * non-nullable closure would silently deny them. Note that the Gate resolves
 * the user from the default auth guard.
 */
final class AccessHelper
{
    public const APP_GATE = 'noerd.access-app';

    public const OBJECT_READ_GATE = 'noerd.object-read';

    public const OBJECT_WRITE_GATE = 'noerd.object-write';

    public const OBJECT_DELETE_GATE = 'noerd.object-delete';

    /**
     * @param  string|null  $appName  tenant_apps.name in any case; null/'' (no app known) is allowed
     */
    public static function canAccessApp(?string $appName): bool
    {
        if ($appName === null || $appName === '') {
            return true;
        }

        return !Gate::has(self::APP_GATE) || Gate::allows(self::APP_GATE, $appName);
    }

    /**
     * @param  class-string|null  $modelClass  null (no model known) is allowed
     */
    public static function canReadObject(?string $modelClass): bool
    {
        return self::check(self::OBJECT_READ_GATE, $modelClass);
    }

    /**
     * @param  class-string|null  $modelClass  null (no model known) is allowed
     */
    public static function canWriteObject(?string $modelClass): bool
    {
        return self::check(self::OBJECT_WRITE_GATE, $modelClass);
    }

    /**
     * @param  class-string|null  $modelClass  null (no model known) is allowed
     */
    public static function canDeleteObject(?string $modelClass): bool
    {
        return self::check(self::OBJECT_DELETE_GATE, $modelClass);
    }

    private static function check(string $gate, ?string $modelClass): bool
    {
        if ($modelClass === null) {
            return true;
        }

        return !Gate::has($gate) || Gate::allows($gate, $modelClass);
    }
}
