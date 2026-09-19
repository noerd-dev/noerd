<?php

declare(strict_types=1);

namespace Noerd\Commands\Concerns;

use Illuminate\Support\Facades\File;

/**
 * What a host project needs once it holds a LOCAL module under app-modules/:
 * the Composer path repository that makes it requirable and the phpunit suite
 * that runs its tests. Deliberately not part of `noerd:install` — a project that
 * only consumes modules from a registry never needs either. Called by whatever
 * creates the first local module (`noerd:make-module`, a boilerplate installer).
 */
trait PreparesModuleWorkspace
{
    protected function prepareModuleWorkspace(): void
    {
        File::ensureDirectoryExists(base_path('app-modules'));

        $this->ensureModulePathRepository();
        $this->ensureModulesTestsuite();
    }

    /**
     * Add the `app-modules/*` path repository to the host's composer.json.
     * The file is decoded into objects, not arrays, so an empty `{}` of the host
     * (e.g. "config": {"allow-plugins": {}}) is written back unchanged.
     */
    protected function ensureModulePathRepository(): void
    {
        $composerPath = base_path('composer.json');

        if (! File::exists($composerPath)) {
            $this->warn('composer.json not found, skipping the app-modules path repository.');

            return;
        }

        $composer = json_decode((string) File::get($composerPath));

        if (! is_object($composer)) {
            $this->warn('Failed to parse composer.json, skipping the app-modules path repository.');

            return;
        }

        $repositories = $composer->repositories ?? [];

        foreach ((array) $repositories as $repository) {
            if (is_object($repository)
                && ($repository->type ?? null) === 'path'
                && str_starts_with((string) ($repository->url ?? ''), 'app-modules')) {
                return;
            }
        }

        $repository = (object) [
            'type' => 'path',
            'url' => 'app-modules/*',
            'options' => (object) ['symlink' => true],
        ];

        if (is_object($repositories)) {
            $repositories->{'app-modules'} = $repository;
        } else {
            $repositories[] = $repository;
        }

        $composer->repositories = $repositories;

        File::put(
            $composerPath,
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n",
        );

        $this->line('<info>Updated:</info> composer.json (added the app-modules/* path repository)');
    }

    /**
     * Add the app-modules testsuite to the host's phpunit.xml.
     */
    protected function ensureModulesTestsuite(): void
    {
        $phpunitPath = base_path('phpunit.xml');

        if (! File::exists($phpunitPath)) {
            return;
        }

        $content = File::get($phpunitPath);

        if (str_contains($content, './app-modules/*/tests')) {
            return;
        }

        $testsuite = '        <testsuite name="Modules"><directory suffix="Test.php">./app-modules/*/tests</directory></testsuite>';

        if (! str_contains($content, '</testsuites>')) {
            $this->warn('Could not find </testsuites> in phpunit.xml. Please add the following testsuite manually:');
            $this->line($testsuite);

            return;
        }

        File::put($phpunitPath, str_replace('</testsuites>', $testsuite . "\n    </testsuites>", $content));
        $this->line('<info>Updated:</info> phpunit.xml (added the app-modules testsuite)');
    }
}
