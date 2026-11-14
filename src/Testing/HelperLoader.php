<?php

declare(strict_types=1);

namespace Noerd\Testing;

/**
 * Loads the global test helpers from `src/Testing/helpers.php`.
 *
 * The helpers are deliberately NOT part of the production composer autoload —
 * a consumer application must never load test functions on every request — and
 * `autoload-dev` does not help either, because composer only ever dumps the
 * dev autoload of the ROOT package, not of a dependency. Consumers therefore
 * need an explicit load, and the path differs per installation
 * (`vendor/noerd/noerd/` vs. `app-modules/noerd/` for a submodule).
 *
 * This class lives in the package's regular psr-4 map (`Noerd\Testing\`), so it
 * resolves through the autoloader in both layouts while its file — and with it
 * `helpers.php` — is only ever read once something asks for it:
 *
 *     // tests/Pest.php of the host application or of any module
 *     \Noerd\Testing\HelperLoader::load();
 *
 * Suites that bind `Noerd\Testing\TestCase` (or `Noerd\Testing\ModuleTestCase`)
 * get the helpers through that class and need no call of their own.
 */
final class HelperLoader
{
    /**
     * Make the global test helpers available. Safe to call repeatedly — the
     * file is included once and every helper is `function_exists`-guarded.
     */
    public static function load(): void
    {
        require_once __DIR__ . '/helpers.php';
    }
}
