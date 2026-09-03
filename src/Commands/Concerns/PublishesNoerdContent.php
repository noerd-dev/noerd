<?php

declare(strict_types=1);

namespace Noerd\Commands\Concerns;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Noerd\Services\FrontendScaffolder;

/**
 * The publishing half of `noerd:install` / `noerd:update`: the setup app
 * configs, the package config, the phpunit test suite entry, the frontend
 * scaffold and the public assets. Both commands run the exact same steps —
 * install adds the one-time setup on top (migrations, tenant, admin user,
 * demo app), update only re-publishes.
 */
trait PublishesNoerdContent
{
    use PublishesConfigDirectory;

    /**
     * Copy directory contents recursively (see PublishesConfigDirectory).
     */
    protected function copyDirectoryContents(string $sourceDir, string $targetDir): array
    {
        return $this->publishConfigDirectory($sourceDir, $targetDir);
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
            $scaffolder = new FrontendScaffolder(base_path(), $this->detectNodeVersion());

            $this->displayFrontendSummary($scaffolder->scaffold());

            // Install whatever the scaffolder added to package.json
            $this->installNpmPackages($scaffolder->missingNpmPackages());

            // Update Livewire component layout
            $this->updateLivewireConfig();

            // Update composer.json repositories
            $this->updateComposerRepositories();

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
     * Install the npm packages the scaffolder added to package.json
     *
     * @param  array<int, string>  $packages
     */
    protected function installNpmPackages(array $packages): void
    {
        if ($packages === []) {
            $this->line('<comment>All required npm packages are already declared.</comment>');

            return;
        }

        $this->line('<comment>Installing npm packages...</comment>');

        // Process handles the working directory and argument escaping — the
        // previous string-built `cd <path> && npm install ...` broke on paths
        // with spaces and discarded npm's diagnostics on failure.
        $result = Process::path(base_path())
            ->timeout(300)
            ->run(array_merge(['npm', 'install'], $packages, ['--save-dev']));

        if ($result->failed()) {
            $this->warn('Failed to install npm packages:');
            $this->warn(mb_trim($result->errorOutput() ?: $result->output()));
            $this->warn('You may need to run the following manually:');
            $this->warn('npm install ' . implode(' ', $packages) . ' --save-dev');
        } else {
            $this->line('<info>NPM packages installed successfully.</info>');
        }
    }

    /**
     * Detect the installed Node version so the scaffolder can pin compatible build tooling
     */
    protected function detectNodeVersion(): ?string
    {
        $result = Process::run('node -v');

        if (! $result->successful() || mb_trim($result->output()) === '') {
            return null;
        }

        return mb_trim($result->output());
    }

    /**
     * Update Livewire config to use noerd layout
     */
    protected function updateLivewireConfig(): void
    {
        $configPath = base_path('config/livewire.php');

        if (! File::exists($configPath)) {
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
     * Display summary of operations
     */
    protected function displaySummary(array $results): void
    {
        $this->displayPublishSummary($results);
    }

    /**
     * Update composer.json to add repositories configuration
     */
    protected function updateComposerRepositories(): void
    {
        $composerPath = base_path('composer.json');

        if (! File::exists($composerPath)) {
            $this->warn('composer.json not found, skipping repositories update');
            return;
        }

        $composerContent = File::get($composerPath);
        $composerData = json_decode($composerContent, true);

        if (! $composerData) {
            $this->warn('Failed to parse composer.json, skipping repositories update');
            return;
        }

        // Check if repositories already exists
        if (isset($composerData['repositories'])) {
            // Check if our path repository already exists
            foreach ($composerData['repositories'] as $repo) {
                if (isset($repo['type']) && $repo['type'] === 'path'
                    && isset($repo['url']) && $repo['url'] === 'app-modules/*') {
                    $this->line('Repositories configuration already exists in composer.json');
                    return;
                }
            }
        } else {
            $composerData['repositories'] = [];
        }

        // Add the path repository
        $composerData['repositories'][] = [
            'type' => 'path',
            'url' => 'app-modules/*',
            'options' => [
                'symlink' => true,
            ],
        ];

        // Write back to composer.json with pretty formatting
        $newContent = json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (File::put($composerPath, $newContent) !== false) {
            $this->line('Added repositories configuration to composer.json');
        } else {
            $this->warn('Failed to update composer.json');
        }
    }

    /**
     * Publish the noerd config file to the application's config directory
     */
    protected function publishNoerdConfig(): void
    {
        $targetPath = base_path('config/noerd.php');

        // Try multiple possible source locations for the stub
        $possibleSources = [
            dirname(__DIR__, 3) . '/stubs/noerd.php.stub', // app-modules/noerd/stubs
            base_path('vendor/noerd/noerd/stubs/noerd.php.stub'), // vendor installation
        ];

        $sourcePath = null;
        foreach ($possibleSources as $path) {
            if (File::exists($path)) {
                $sourcePath = $path;
                break;
            }
        }

        if ($sourcePath === null) {
            $this->warn('Source config stub not found. Tried:');
            foreach ($possibleSources as $path) {
                $this->warn('  - ' . $path);
            }

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

    /**
     * Ensure app-modules directory exists with .gitkeep file
     */
    protected function ensureAppModulesDirectory(): void
    {
        $appModulesPath = base_path('app-modules');

        if (! File::isDirectory($appModulesPath)) {
            if (! File::makeDirectory($appModulesPath, 0755, true)) {
                $this->warn('Failed to create app-modules directory');
                return;
            }
            $this->line('Created app-modules directory');
        } else {
            $this->line('<comment>app-modules directory already exists</comment>');
        }

        $gitkeepPath = $appModulesPath . DIRECTORY_SEPARATOR . '.gitkeep';

        if (! File::exists($gitkeepPath)) {
            if (File::put($gitkeepPath, '') !== false) {
                $this->line('Created .gitkeep file in app-modules directory');
            } else {
                $this->warn('Failed to create .gitkeep file');
            }
        } else {
            $this->line('<comment>.gitkeep already exists in app-modules directory</comment>');
        }
    }

    /**
     * Update phpunit.xml with the app-modules testsuite configuration
     */
    protected function updatePhpunitXml(): void
    {
        $phpunitPath = base_path('phpunit.xml');

        if (! File::exists($phpunitPath)) {
            $this->warn('phpunit.xml not found, skipping phpunit configuration.');

            return;
        }

        $phpunitContent = File::get($phpunitPath);

        // Check if the app-modules testsuite already exists
        if (str_contains($phpunitContent, './app-modules/*/tests')) {
            $this->line('<comment>app-modules testsuite already configured in phpunit.xml.</comment>');

            return;
        }

        // The testsuite entry to add
        $newTestsuite = '        <testsuite name="Modules"><directory suffix="Test.php">./app-modules/*/tests</directory></testsuite>';

        // Try to find the closing </testsuites> tag and insert before it
        if (str_contains($phpunitContent, '</testsuites>')) {
            $phpunitContent = str_replace(
                '</testsuites>',
                $newTestsuite . "\n    </testsuites>",
                $phpunitContent,
            );

            if (File::put($phpunitPath, $phpunitContent) !== false) {
                $this->line('<info>Added app-modules testsuite to phpunit.xml.</info>');
            } else {
                $this->warn('Failed to update phpunit.xml');
            }
        } else {
            $this->warn('Could not find </testsuites> tag in phpunit.xml. Please add the following testsuite manually:');
            $this->line($newTestsuite);
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
