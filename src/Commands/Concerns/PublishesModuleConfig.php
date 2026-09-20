<?php

declare(strict_types=1);

namespace Noerd\Commands\Concerns;

use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * The PHP config file(s) and the setup-app YAML a module ships — published the
 * same way by every install and update command, so no module carries its own
 * `publishConfig()` copy any more.
 *
 * One policy for a file the host may have edited:
 *  - missing            → published
 *  - existing, install  → overwritten under --force, otherwise asked (default: keep)
 *  - existing, update   → NEVER overwritten, not even under --force: `noerd:update-all
 *                         --force` refreshes YAML, it must not reset a host's settings.
 *                         Top-level keys the host copy is missing are reported instead.
 */
trait PublishesModuleConfig
{
    /**
     * The module root (composer.json, config/, stubs/, app-configs/).
     */
    abstract protected function getModuleRoot(): string;

    /**
     * The config files to publish into the host's config/ directory. A list
     * entry names a file of {module}/config/; a keyed entry maps the target
     * file name to a source relative to the module root.
     *
     * Example: ['payment.php'] or ['noerd-activities.php' => 'stubs/noerd-activities.php.stub']
     *
     * @return array<int|string, string>
     */
    protected function getConfigFiles(): array
    {
        return [];
    }

    protected function publishModuleConfigs(bool $update = false): void
    {
        foreach ($this->getConfigFiles() as $target => $source) {
            if (is_int($target)) {
                $target = $source;
                $source = 'config/' . $source;
            }

            $this->publishModuleConfig(
                $this->getModuleRoot() . DIRECTORY_SEPARATOR . $source,
                config_path($target),
                $update,
            );
        }
    }

    protected function publishModuleConfig(string $sourcePath, string $targetPath, bool $update = false): void
    {
        $displayPath = 'config/' . basename($targetPath);

        if (! File::exists($sourcePath)) {
            $this->warn("Config source not found: {$sourcePath}");

            return;
        }

        if (File::exists($targetPath)) {
            if ($update) {
                $this->reportMissingConfigKeys($sourcePath, $targetPath, $displayPath);

                return;
            }

            $overwrite = (bool) $this->option('force')
                || ($this->input->isInteractive()
                    && $this->confirm("Config file {$displayPath} already exists. Overwrite?", false));

            if (! $overwrite) {
                $this->line("<comment>Kept existing config file:</comment> {$displayPath}");

                return;
            }
        }

        File::ensureDirectoryExists(dirname($targetPath));
        File::copy($sourcePath, $targetPath);
        $this->line("<info>Published config file:</info> {$displayPath}");
    }

    /**
     * Publish the YAML the module contributes to the SETUP app
     * ({module}/app-configs/setup/**: collections, lists, details) into the
     * host's app-configs/setup. Existing files are kept unless --force — they may
     * carry local edits, and the step must stay prompt-free so it can run on
     * every update. The navigation is never copied: entries are added through
     * ensureSetupNavigation().
     */
    protected function publishSetupConfigs(): void
    {
        $sourceDir = $this->getModuleRoot() . '/app-configs/setup';

        if (! is_dir($sourceDir)) {
            return;
        }

        $targetDir = base_path('app-configs/setup');

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $relativePath = mb_substr($item->getPathname(), mb_strlen($sourceDir) + 1);
            $targetPath = $targetDir . DIRECTORY_SEPARATOR . $relativePath;

            if ($item->isDir() || $relativePath === 'navigation.yml') {
                continue;
            }

            if (File::exists($targetPath) && ! $this->option('force')) {
                continue;
            }

            File::ensureDirectoryExists(dirname($targetPath));
            File::copy($item->getPathname(), $targetPath);
            $this->line("<info>Published:</info> app-configs/setup/{$relativePath}");
        }
    }

    /**
     * An update leaves the host's config alone, so a key added by a newer module
     * version would go unnoticed (the merged package default still applies).
     */
    private function reportMissingConfigKeys(string $sourcePath, string $targetPath, string $displayPath): void
    {
        try {
            $shipped = require $sourcePath;
            $current = require $targetPath;
        } catch (Throwable) {
            return;
        }

        if (! is_array($shipped) || ! is_array($current)) {
            return;
        }

        $missing = array_keys(array_diff_key($shipped, $current));

        if ($missing !== []) {
            $this->line("<comment>{$displayPath} does not declare: " . implode(', ', $missing) . ' (the package defaults apply).</comment>');
        }
    }
}
