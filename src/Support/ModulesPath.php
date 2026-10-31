<?php

declare(strict_types=1);

namespace Noerd\Support;

/**
 * The directory holding a project's LOCAL modules (`app-modules` unless
 * `noerd.generators.modules_path` says otherwise). Every scaffolder, the YAML
 * resolution and the linter build their module paths through this class, so the
 * setting is honoured everywhere or nowhere.
 */
final class ModulesPath
{
    /**
     * The modules directory relative to the project root, without slashes
     * around it (`app-modules`).
     */
    public static function relative(): string
    {
        $configured = mb_trim((string) (config('noerd.generators.modules_path') ?? ''), '/');

        return $configured !== '' ? $configured : 'app-modules';
    }

    /**
     * The absolute modules directory, or an absolute path below it.
     */
    public static function absolute(string $path = ''): string
    {
        $path = mb_ltrim($path, '/');

        return base_path(self::relative() . ($path !== '' ? '/' . $path : ''));
    }
}
