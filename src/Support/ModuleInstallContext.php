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

    /** @var array<int, string> */
    private static array $deferredNpmPackages = [];

    private static bool $deferredNpmBuild = false;

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
     * Hand the npm work of a nested base installation to the module install that
     * started it. Running node halfway through would compile a project the
     * module — and whatever it pulls in, e.g. the website boilerplate behind the
     * CMS — has not been added to yet, and would ask the same question twice.
     *
     * @param  array<int, string>  $packages
     */
    public static function deferNpm(array $packages = [], bool $build = false): void
    {
        self::$deferredNpmPackages = array_values(array_unique([...self::$deferredNpmPackages, ...$packages]));
        self::$deferredNpmBuild = self::$deferredNpmBuild || $build;
    }

    /**
     * Whether npm work is still waiting to be handed over.
     */
    public static function hasDeferredNpm(): bool
    {
        return self::$deferredNpmPackages !== [] || self::$deferredNpmBuild;
    }

    /**
     * The deferred npm packages, cleared as they are handed over.
     *
     * @return array<int, string>
     */
    public static function takeDeferredNpmPackages(): array
    {
        $packages = self::$deferredNpmPackages;
        self::$deferredNpmPackages = [];

        return $packages;
    }

    /**
     * Whether a deferred frontend build is waiting, cleared as it is handed over.
     */
    public static function takeDeferredNpmBuild(): bool
    {
        $build = self::$deferredNpmBuild;
        self::$deferredNpmBuild = false;

        return $build;
    }

    /**
     * Reset the nesting counter and the deferred npm work (tests only — a
     * throwing callback restores the counter itself through the finally block).
     */
    public static function reset(): void
    {
        self::$depth = 0;
        self::$deferredNpmPackages = [];
        self::$deferredNpmBuild = false;
    }
}
