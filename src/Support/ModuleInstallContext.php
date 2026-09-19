<?php

declare(strict_types=1);

namespace Noerd\Support;

/**
 * Marks the stretch of a module installation that runs a DEPENDENCY module's
 * install command (media for the CMS, party for the CRM, …).
 *
 * A dependency is not a second installation the user started: it must never ask
 * its own questions about tenants. The app that requires it assigns it to the
 * tenants it was assigned to itself — see HasModuleInstallation::getRequiredAppKeys().
 */
final class ModuleInstallContext
{
    private static int $depth = 0;

    /**
     * Run a nested install command as a dependency of the current one.
     */
    public static function asDependency(callable $callback): mixed
    {
        self::$depth++;

        try {
            return $callback();
        } finally {
            self::$depth--;
        }
    }

    /**
     * True while a dependency module is being installed by another module.
     */
    public static function isDependencyInstall(): bool
    {
        return self::$depth > 0;
    }

    /**
     * Reset the nesting counter (tests only — a throwing callback restores it
     * itself through the finally block).
     */
    public static function reset(): void
    {
        self::$depth = 0;
    }
}
