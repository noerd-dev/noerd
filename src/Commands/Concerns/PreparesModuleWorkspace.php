<?php

declare(strict_types=1);

namespace Noerd\Commands\Concerns;

use Illuminate\Support\Facades\File;
use Noerd\Support\ModulesPath;

/**
 * What a host project needs once it holds a LOCAL module under the modules
 * directory (`app-modules/` by default): the Composer path repository that makes
 * it requirable and the phpunit suite that runs its tests. Deliberately not part
 * of `noerd:install` — a project that only consumes modules from a registry never
 * needs either. Called by whatever creates the first local module
 * (`noerd:make-module`, a boilerplate installer).
 */
trait PreparesModuleWorkspace
{
    protected function prepareModuleWorkspace(): void
    {
        File::ensureDirectoryExists(ModulesPath::absolute());

        $this->ensureModulePathRepository();
        $this->ensureModulesTestsuite();
    }

    /**
     * Add the `{modules}/*` path repository to the host's composer.json.
     * The file is decoded into objects, not arrays, so an empty `{}` of the host
     * (e.g. "config": {"allow-plugins": {}}) is written back unchanged.
     */
    protected function ensureModulePathRepository(): void
    {
        $modules = ModulesPath::relative();
        $composerPath = base_path('composer.json');

        if (! File::exists($composerPath)) {
            $this->warn("composer.json not found, skipping the {$modules} path repository.");

            return;
        }

        $composer = json_decode((string) File::get($composerPath));

        if (! is_object($composer)) {
            $this->warn("Failed to parse composer.json, skipping the {$modules} path repository.");

            return;
        }

        $repositories = $composer->repositories ?? [];

        foreach ((array) $repositories as $repository) {
            if (is_object($repository)
                && ($repository->type ?? null) === 'path'
                && str_starts_with((string) ($repository->url ?? ''), $modules)) {
                return;
            }
        }

        $repository = (object) [
            'type' => 'path',
            'url' => "{$modules}/*",
            'options' => (object) ['symlink' => true],
        ];

        if (is_object($repositories)) {
            $repositories->{$modules} = $repository;
        } else {
            $repositories[] = $repository;
        }

        $composer->repositories = $repositories;

        File::put(
            $composerPath,
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n",
        );

        $this->line("<info>Updated:</info> composer.json (added the {$modules}/* path repository)");
    }

    /**
     * Add the modules testsuite to the host's phpunit.xml.
     */
    protected function ensureModulesTestsuite(): void
    {
        $modules = ModulesPath::relative();
        $phpunitPath = base_path('phpunit.xml');

        if (! File::exists($phpunitPath)) {
            return;
        }

        $content = File::get($phpunitPath);

        if (str_contains($content, "./{$modules}/*/tests")) {
            return;
        }

        $testsuite = "        <testsuite name=\"Modules\"><directory suffix=\"Test.php\">./{$modules}/*/tests</directory></testsuite>";

        if (! str_contains($content, '</testsuites>')) {
            $this->warn('Could not find </testsuites> in phpunit.xml. Please add the following testsuite manually:');
            $this->line($testsuite);

            return;
        }

        File::put($phpunitPath, str_replace('</testsuites>', $testsuite . "\n    </testsuites>", $content));
        $this->line("<info>Updated:</info> phpunit.xml (added the {$modules} testsuite)");
    }
}
