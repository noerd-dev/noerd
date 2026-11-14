<?php

declare(strict_types=1);

namespace Noerd\Commands\Support;

/**
 * The idempotent migration that registers a tenant app (`tenant_apps` row) —
 * rendered from the stub a module ships (`app-configs/stubs/add_{key}_tenant_app.php.stub`)
 * or, for an app in the project root, from the core's own copy of that stub.
 * Every app is registered this way, so a deployment that only runs
 * `php artisan migrate` knows it.
 *
 * @internal
 */
final class TenantAppMigration
{
    /** The core's copy of the stub (also the template noerd:make-module ships). */
    public static function coreStubPath(): string
    {
        return dirname(__DIR__) . '/stubs/module/tenant-app-migration.stub';
    }

    /**
     * The file name pattern of the registration migration of an app.
     */
    public static function pattern(string $moduleKey): string
    {
        return database_path("migrations/*_add_{$moduleKey}_tenant_app.php");
    }

    /**
     * Write the migration with a fresh timestamp; returns its file name.
     */
    public static function write(string $stubPath, string $moduleKey, string $appName, string $title, string $icon, string $route): string
    {
        $filename = date('Y_m_d_His') . "_add_{$moduleKey}_tenant_app.php";
        $targetPath = database_path("migrations/{$filename}");
        $escape = static fn(string $value): string => addcslashes($value, "'\\");

        $content = str_replace(
            ['{{APP_TITLE}}', '{{APP_NAME}}', '{{APP_ICON}}', '{{APP_ROUTE}}'],
            [$escape($title), $escape($appName), $escape($icon), $escape($route)],
            (string) file_get_contents($stubPath),
        );

        if (! is_dir(dirname($targetPath))) {
            mkdir(dirname($targetPath), 0755, true);
        }

        file_put_contents($targetPath, $content);

        return $filename;
    }
}
