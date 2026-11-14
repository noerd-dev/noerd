<?php

declare(strict_types=1);

namespace Noerd\Commands\Concerns;

use Illuminate\Support\Facades\File;

/**
 * The line in the host's `tests/Pest.php` that loads the noerd test helpers,
 * and its migration: up to noerd 0.17 the test API lived in `Noerd\Tests\`,
 * since 0.18 it ships as `Noerd\Testing\`. `noerd:make-module` writes the line,
 * `noerd:update` rewrites an old one.
 */
trait LoadsTestHelpers
{
    /** The test-helper loader as the host's tests/Pest.php calls it. */
    protected function testHelperLoaderLine(): string
    {
        return '\\Noerd\\Testing\\HelperLoader::load();';
    }

    /**
     * Rewrite a `Noerd\Tests\HelperLoader` reference in the host's
     * `tests/Pest.php` to `Noerd\Testing\HelperLoader`. Idempotent: a file
     * without the old name is left alone. True when the file holds (or, in a
     * dry run, would hold) a rewritten reference.
     */
    protected function migrateTestHelperLoader(bool $dryRun = false): bool
    {
        $pestPath = base_path('tests/Pest.php');

        if (! File::exists($pestPath)) {
            return false;
        }

        $content = File::get($pestPath);
        $migrated = str_replace('Noerd\\Tests\\HelperLoader', 'Noerd\\Testing\\HelperLoader', $content);

        if ($migrated === $content) {
            return false;
        }

        if ($dryRun) {
            $this->line('<comment>tests/Pest.php would load the noerd test helpers through Noerd\\Testing\\HelperLoader (was Noerd\\Tests\\HelperLoader).</comment>');

            return true;
        }

        File::put($pestPath, $migrated);
        $this->line('<info>Updated:</info> tests/Pest.php (loads the noerd test helpers through Noerd\\Testing\\HelperLoader)');

        return true;
    }
}
