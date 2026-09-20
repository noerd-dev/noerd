<?php

declare(strict_types=1);

namespace Noerd\Commands\Concerns;

use Exception;
use Illuminate\Support\Facades\File;
use Noerd\Services\FrontendScaffolder;

/**
 * The publishing half of `noerd:install` / `noerd:update`: the setup app
 * configs, the package config, the frontend scaffold, the Livewire layout and
 * the public assets. Both commands run the exact same sequence
 * (publishNoerdContent()) — install adds the one-time setup on top (migrations,
 * tenant, admin user, demo app), update only re-publishes.
 */
trait PublishesNoerdContent
{
    use GuardsPublishTargets;
    use PublishesConfigDirectory;

    use RegistersBoostPackage;
    use RunsNpmBuild;

    /**
     * Publish everything the package ships into the host: the setup app configs,
     * config/noerd.php, the frontend scaffold, the public assets and the Boost
     * registration. False when the setup app configs could not be published
     * (already reported).
     */
    protected function publishNoerdContent(): bool
    {
        if (! $this->publishSetupAppConfigs()) {
            return false;
        }

        $this->publishNoerdConfig();
        $this->setupFrontendAssets();
        $this->updateLivewireConfig();
        $this->publishNoerdAssets();
        $this->registerNoerdBoostPackage();

        return true;
    }

    /**
     * Publish the setup app's YAML configs into the host's app-configs/setup.
     *
     * Published quietly: the setup app is the package's own content, the same
     * ~15 files on every installation, and naming each one buries the steps the
     * reader has to act on (the admin user, the migrations) in a wall of paths.
     * The summary reports what happened; a prompt for an existing file still shows.
     */
    protected function publishSetupAppConfigs(): bool
    {
        $sourceDir = dirname(__DIR__, 3) . '/app-configs/setup';

        if (! File::isDirectory($sourceDir)) {
            $this->error("Source directory not found: {$sourceDir}");

            return false;
        }

        $this->displayPublishSummary($this->publishConfigDirectory($sourceDir, base_path('app-configs/setup'), quiet: true));

        return true;
    }

    /**
     * Enable the noerd guideline and skills in the host's boost.json and render them.
     */
    protected function registerNoerdBoostPackage(): void
    {
        $this->registerBoostPackage(dirname(__DIR__, 3));
    }

    /**
     * Read a boolean option that may not exist on a subclass's redefined
     * signature (NoerdUpdateCommand and test fixtures override $signature).
     */
    protected function boolOption(string $name): bool
    {
        return $this->input->hasOption($name) && (bool) $this->option($name);
    }

    /**
     * Publish the package's public assets (fonts + built Vite bundle). Assets
     * are published ONLY from console commands — a web request must never
     * write to public/ (see NoerdServiceProvider).
     */
    protected function publishNoerdAssets(): void
    {
        if (! $this->publishTargetsCurrentInstallation('noerd-assets')) {
            $this->warn('Skipping the noerd asset publish: it would write outside this installation.');

            return;
        }

        $this->call('vendor:publish', [
            '--tag' => 'noerd-assets',
            '--force' => true,
            '--no-interaction' => true,
        ]);
    }

    /**
     * Setup frontend assets and configuration
     */
    protected function setupFrontendAssets(): void
    {
        $this->line('');
        $this->info('Setting up frontend assets...');

        try {
            $scaffolder = new FrontendScaffolder(base_path());

            $this->displayFrontendSummary($scaffolder->scaffold());

            // Install whatever the scaffolder added to package.json — deferred
            // to the end of the run while this is a module's base installation.
            $this->installNpmPackages($scaffolder->missingNpmPackages());

            $this->line('<info>Frontend assets setup completed successfully.</info>');
        } catch (Exception $e) {
            // Surface the failure loudly — a broken frontend scaffold must not
            // end in a green "Application ready!" message.
            $this->error('Frontend assets setup failed: ' . $e->getMessage());
            $this->error('Fix the issue and re-run: php artisan noerd:update');
        }
    }

    /**
     * Display which frontend files were created, patched or left alone
     *
     * @param  array<int, array{file: string, action: string, detail: string}>  $results
     */
    protected function displayFrontendSummary(array $results): void
    {
        $rows = [];

        foreach ($results as $result) {
            $rows[] = [
                $result['file'],
                $this->formatFrontendAction($result['action']),
                $result['detail'],
            ];
        }

        $this->table(['File', 'Action', 'Detail'], $rows);

        foreach ($results as $result) {
            if ($result['action'] === FrontendScaffolder::ACTION_WARNING) {
                $this->warn($result['file'] . ': ' . $result['detail']);
            }
        }
    }

    /**
     * Update Livewire config to use noerd layout
     */
    protected function updateLivewireConfig(): void
    {
        $configPath = base_path('config/livewire.php');

        if (! File::exists($configPath)) {
            // livewire:config is vendor:publish --force behind a nicer name.
            if (! $this->publishTargetsCurrentInstallation('livewire:config')) {
                $this->warn('Skipping the Livewire config publish: it would write outside this installation.');
                $this->warn('Please set component_layout to noerd::layouts.app manually.');

                return;
            }

            $this->line('<comment>Publishing Livewire config file...</comment>');
            $this->call('livewire:config', ['--no-interaction' => true]);
        }

        if (! File::exists($configPath)) {
            $this->warn('config/livewire.php could not be published, skipping Livewire layout configuration.');

            return;
        }

        $configContent = File::get($configPath);

        if (str_contains($configContent, "'noerd::layouts.app'")) {
            $this->line('<comment>Livewire component_layout already set to noerd layout.</comment>');

            return;
        }

        $updated = str_replace(
            "'layouts::app'",
            "'noerd::layouts.app'",
            $configContent,
        );

        if ($updated === $configContent) {
            $this->warn('Could not find default component_layout value in config/livewire.php. Please set it manually to: noerd::layouts.app');

            return;
        }

        if (File::put($configPath, $updated) !== false) {
            $this->line('<info>Updated Livewire component_layout to noerd::layouts.app.</info>');
        } else {
            $this->warn('Failed to update config/livewire.php. Please manually set component_layout to: noerd::layouts.app');
        }
    }

    /**
     * Publish the noerd config file to the application's config directory
     */
    protected function publishNoerdConfig(): void
    {
        $targetPath = base_path('config/noerd.php');

        // The package root is the same wherever the package is installed (vendor/ or
        // a path repository under app-modules/).
        $sourcePath = dirname(__DIR__, 3) . '/stubs/noerd.php.stub';

        if (! File::exists($sourcePath)) {
            $this->warn("Source config stub not found: {$sourcePath}");

            return;
        }

        if (File::exists($targetPath)) {
            $this->refreshExistingNoerdConfig($sourcePath, $targetPath);

            return;
        }

        if (File::copy($sourcePath, $targetPath)) {
            $this->line('<info>Published config/noerd.php successfully.</info>');
        } else {
            $this->warn('Failed to publish config/noerd.php');
        }
    }

    /**
     * An existing config/noerd.php belongs to the host — never clobber it
     * silently. The stub is diffed against it: missing top-level keys are
     * reported (the documented contract is that noerd:update carries new keys
     * into existing installations), and an overwrite happens only via --force
     * or an explicit confirmation. No .bak is written — the host config is
     * under version control.
     */
    protected function refreshExistingNoerdConfig(string $sourcePath, string $targetPath): void
    {
        $missing = [];
        try {
            $stub = require $sourcePath;
            $current = require $targetPath;
            if (is_array($stub) && is_array($current)) {
                $missing = array_keys(array_diff_key($stub, $current));
            }
        } catch (Exception) {
            // A config that cannot be evaluated is treated as customized.
        }

        if (! $this->option('force')) {
            if ($missing === []) {
                $this->line('<comment>config/noerd.php already exists and declares every stub key — left untouched.</comment>');

                return;
            }

            $this->warn('config/noerd.php is missing new top-level keys: ' . implode(', ', $missing));

            if (! $this->input->isInteractive()
                || ! $this->confirm('Overwrite config/noerd.php with the current stub?', false)) {
                $this->line('<comment>Skipped config/noerd.php publishing. Add the missing keys manually (see stubs/noerd.php.stub).</comment>');

                return;
            }
        }

        $this->line('<comment>Overwriting config/noerd.php...</comment>');

        if (File::copy($sourcePath, $targetPath)) {
            $this->line('<info>Published config/noerd.php successfully.</info>');
        } else {
            $this->warn('Failed to publish config/noerd.php');
        }
    }

    private function formatFrontendAction(string $action): string
    {
        return match ($action) {
            FrontendScaffolder::ACTION_CREATED => '<info>created</info>',
            FrontendScaffolder::ACTION_PATCHED => '<info>patched</info>',
            FrontendScaffolder::ACTION_WARNING => '<comment>warning</comment>',
            default => '<comment>skipped</comment>',
        };
    }
}
