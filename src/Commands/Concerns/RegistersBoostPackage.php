<?php

declare(strict_types=1);

namespace Noerd\Commands\Concerns;

use Noerd\Support\BoostConfig;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Enable a package's Laravel Boost guideline and skills in the host project.
 *
 * Boost only renders the guideline (`resources/boost/guidelines/core.blade.php`)
 * and the skills (`resources/boost/skills/{name}/SKILL.md`) of a third-party
 * package when the package is listed under `packages` in the host's boost.json.
 * That step was easy to forget, so every noerd install/update command registers
 * its own package and refreshes the rendered agent files (`boost:update`).
 *
 * Nothing here ever fails the calling command: without a boost.json (Boost not
 * installed) a hint is printed and that is all.
 */
trait RegistersBoostPackage
{
    /**
     * @param  string  $packageRoot  The package directory holding composer.json and resources/boost
     */
    protected function registerBoostPackage(string $packageRoot): void
    {
        try {
            $name = $this->boostPackageName($packageRoot);
            $skills = $this->boostSkillNames($packageRoot);

            if ($name === null || (! $this->shipsBoostGuideline($packageRoot) && $skills === [])) {
                return;
            }

            $config = $this->boostConfig();

            if (! $config->exists()) {
                $this->line("<comment>Laravel Boost is not set up (no boost.json): add \"{$name}\" to its packages after php artisan boost:install to render the agent guidelines.</comment>");

                return;
            }

            if (! $config->isValid()) {
                $this->warn('boost.json is not valid JSON; left untouched.');

                return;
            }

            if (! $this->boostPackageInstalled($name)) {
                $this->warn("{$name} is not installed via Composer (vendor/{$name} missing); Boost would drop it from boost.json again.");

                return;
            }

            $added = $config->ensure([$name], $skills);

            if ($added['packages'] !== []) {
                $this->line("<info>Boost package registered:</info> {$name}");
            } else {
                $this->line("<comment>Boost package already registered:</comment> {$name}");
            }

            if ($added['skills'] !== []) {
                $this->line('<info>Boost skills registered:</info> ' . implode(', ', $added['skills']));
            }

            $this->refreshBoostGuidelines(force: $added['packages'] !== [] || $added['skills'] !== []);
        } catch (Throwable $e) {
            $this->warn('Could not register the package in boost.json: ' . $e->getMessage());
        }
    }

    /**
     * Re-render the agent files. Runs once per process unless a new entry was
     * added — `noerd:update-all` chains many commands and must not repeat it.
     */
    protected function refreshBoostGuidelines(bool $force): void
    {
        if (! $this->boostUpdateAvailable()) {
            $this->line('<comment>Run php artisan boost:update to render the agent guidelines.</comment>');

            return;
        }

        if (! $force && BoostConfig::wasRefreshedInProcess()) {
            return;
        }

        BoostConfig::markRefreshedInProcess();
        $this->runBoostUpdate();
    }

    protected function boostConfig(): BoostConfig
    {
        return BoostConfig::forProject();
    }

    /**
     * Boost writes `packages` back as "configured ∩ discovered", and discovery
     * requires the package under vendor/ (a path-repository symlink qualifies).
     */
    protected function boostPackageInstalled(string $name): bool
    {
        return is_dir(base_path('vendor/' . $name));
    }

    protected function boostUpdateAvailable(): bool
    {
        return $this->getApplication()?->has('boost:update') ?? false;
    }

    protected function runBoostUpdate(): void
    {
        $exitCode = $this->call('boost:update', ['--no-interaction' => true]);

        if ($exitCode !== 0) {
            $this->warn('boost:update did not complete; run php artisan boost:update to render the agent guidelines.');
        }
    }

    /**
     * The Composer package name from the package's composer.json.
     */
    protected function boostPackageName(string $packageRoot): ?string
    {
        $composerFile = $packageRoot . '/composer.json';

        if (! is_file($composerFile)) {
            return null;
        }

        $composer = json_decode((string) file_get_contents($composerFile), true);
        $name = is_array($composer) ? ($composer['name'] ?? null) : null;

        return is_string($name) && $name !== '' ? $name : null;
    }

    protected function shipsBoostGuideline(string $packageRoot): bool
    {
        return is_file($packageRoot . '/resources/boost/guidelines/core.blade.php');
    }

    /**
     * The `name` of every skill Boost will discover: only resources/boost/skills
     * counts (a module's top-level skills/ folder is published by noerd itself and
     * must never be tracked in boost.json — Boost would remove it as stale), and
     * only a SKILL.md whose front matter carries name + description is a skill.
     *
     * @return string[]
     */
    protected function boostSkillNames(string $packageRoot): array
    {
        $names = [];

        foreach (glob($packageRoot . '/resources/boost/skills/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $file = is_file($dir . '/SKILL.md') ? $dir . '/SKILL.md' : $dir . '/SKILL.blade.php';

            if (! is_file($file)) {
                continue;
            }

            if (preg_match('/^\s*---\s*\n(.*?)\n---\s*\n/s', (string) file_get_contents($file), $matches) !== 1) {
                continue;
            }

            try {
                $frontmatter = Yaml::parse($matches[1]);
            } catch (Throwable) {
                continue;
            }

            $name = is_array($frontmatter) ? ($frontmatter['name'] ?? null) : null;
            $description = is_array($frontmatter) ? ($frontmatter['description'] ?? null) : null;

            if (is_string($name) && $name !== '' && is_string($description) && $description !== '') {
                $names[] = $name;
            }
        }

        sort($names);

        return $names;
    }
}
