<?php

declare(strict_types=1);

namespace Noerd\Tests;

/**
 * Loads the global test helpers from `tests/helpers.php`.
 *
 * The helpers are deliberately NOT part of the production composer autoload —
 * a consumer application must never load test functions on every request — and
 * `autoload-dev` does not help either, because composer only ever dumps the
 * dev autoload of the ROOT package, not of a dependency. Consumers therefore
 * need an explicit load, and the path differs per installation
 * (`vendor/noerd/noerd/tests/` vs. `app-modules/noerd/tests/` for a submodule).
 *
 * This class lives in the package's regular psr-4 map (`Noerd\Tests\`), so it
 * resolves through the autoloader in both layouts while its file — and with it
 * `helpers.php` — is only ever read once something asks for it:
 *
 *     // tests/Pest.php of the host application or of any module
 *     \Noerd\Tests\HelperLoader::load();
 *
 * Suites that bind `Noerd\Tests\TestCase` get the helpers through that class
 * and need no call of their own.
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
