<?php

declare(strict_types=1);

namespace Noerd\Commands\Concerns;

use Exception;
use Illuminate\Support\Facades\File;
use Noerd\Commands\Support\FrontendScaffolder;
use Noerd\Support\HostNavigationMerger;
use Symfony\Component\Yaml\Yaml;
use Throwable;

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
    use LoadsTestHelpers;
    use PublishesConfigDirectory;

    use RegistersBoostPackage;
    use RunsNpmBuild;
    /**
     * Config arrays whose KEYS the host chooses (brand presets) — compared as a
     * value, never key by key.
     *
     * @var list<string>
     */
    private const CONFIG_VALUE_MAPS = ['brand.presets', 'brand.overrides'];

    /**
     * Publish everything the package ships into the host: the setup app configs,
     * config/noerd.php, the frontend scaffold, the public assets and the Boost
     * registration. False when the setup app configs could not be published
     * (already reported).
     */
    protected function publishNoerdContent(): bool
    {
        $this->migrateYamlVocabulary();

        if (! $this->publishSetupAppConfigs()) {
            return false;
        }

        $this->publishProfileAppConfigs();

        $this->publishNoerdConfig();
        $this->setupFrontendAssets();
        $this->updateLivewireConfig();
        $this->publishNoerdAssets();
        $this->registerNoerdBoostPackage();
        $this->migrateTestHelperLoader();

        return true;
    }

    /**
     * Publish the setup app's YAML configs into the host's app-configs/setup.
     *
     * Published quietly: the setup app is the package's own content, the same
     * ~15 files on every installation, and naming each one buries the steps the
     * reader has to act on (the admin user, the migrations) in a wall of paths.
     * The summary reports what happened; a prompt for an existing file still shows.
     *
     * The navigation is never copied over an existing one (modules add their
     * entries to it): publishHostNavigation() adds what the core misses.
     */
    protected function publishSetupAppConfigs(): bool
    {
        $sourceDir = $this->getModuleRoot() . '/app-configs/setup';

        if (! File::isDirectory($sourceDir)) {
            $this->error("Source directory not found: {$sourceDir}");

            return false;
        }

        $this->displayPublishSummary($this->publishConfigDirectory($sourceDir, base_path('app-configs/setup'), quiet: true, except: ['navigation.yml']));
        $this->publishHostNavigation('setup');

        return true;
    }

    /**
     * Publish the navigation of the profile area (app-configs/profile) — the
     * same rule as the setup navigation: modules add their entries to it
     * (ensureProfileNavigation()), an update never drops them.
     */
    protected function publishProfileAppConfigs(): void
    {
        $sourceDir = $this->getModuleRoot() . '/app-configs/profile';

        if (File::isDirectory($sourceDir)) {
            $this->publishConfigDirectory($sourceDir, base_path('app-configs/profile'), quiet: true, keepExisting: true, except: ['navigation.yml']);
            $this->publishHostNavigation('profile');
        }
    }

    /**
     * A core navigation (setup, profile) belongs to the host once it exists:
     * the package template is copied only when the host has none (or with
     * --reset-navigation); otherwise the entries the core ships are ADDED where
     * they are missing — host and module entries, their order and their titles
     * stay as they are — and the entries the core retired are dropped
     * (HostNavigationMerger).
     */
    protected function publishHostNavigation(string $folder): void
    {
        $source = $this->getModuleRoot() . "/app-configs/{$folder}/navigation.yml";
        $target = base_path("app-configs/{$folder}/navigation.yml");

        if (! File::exists($source)) {
            return;
        }

        if (! File::exists($target) || $this->boolOption('reset-navigation')) {
            File::ensureDirectoryExists(dirname($target));
            File::copy($source, $target);
            $this->line("<info>Published app-configs/{$folder}/navigation.yml.</info>");

            return;
        }

        $host = Yaml::parse(File::get($target)) ?? [];
        $template = Yaml::parse(File::get($source)) ?? [];

        if (! is_array($host) || ! is_array($template)) {
            $this->warn("app-configs/{$folder}/navigation.yml cannot be read as a navigation — left untouched.");

            return;
        }

        $merged = app(HostNavigationMerger::class)->merge($host, $template);

        if ($merged === $host) {
            return;
        }

        File::put($target, Yaml::dump($merged, 10, 2));
        $this->line("<info>Updated app-configs/{$folder}/navigation.yml:</info> added the missing core entries, kept every existing one.");
    }

    /**
     * Enable the noerd guideline and skills in the host's boost.json and render them.
     */
    protected function registerNoerdBoostPackage(): void
    {
        $this->registerBoostPackage($this->getModuleRoot());
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
        $sourcePath = $this->getModuleRoot() . '/config/noerd.php';

        if (! File::exists($sourcePath)) {
            $this->warn("Source config not found: {$sourcePath}");

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
     * An existing config/noerd.php belongs to the host and is NEVER overwritten
     * — not even with --force, which only refers to the YAML copies. The package
     * config is compared with it instead: keys the package added since the copy
     * was published are reported as missing, keys the package no longer reads as
     * obsolete — both down to the nested settings (`features.currency`), so the
     * host can carry them over by hand. No .bak, no prompt: the host config is
     * under version control. To start over, delete the file and run the command
     * again.
     */
    protected function refreshExistingNoerdConfig(string $sourcePath, string $targetPath): void
    {
        try {
            $stub = require $sourcePath;
            $current = require $targetPath;
        } catch (Throwable) {
            $this->line('<comment>config/noerd.php cannot be evaluated — left untouched.</comment>');

            return;
        }

        if (! is_array($stub) || ! is_array($current)) {
            $this->line('<comment>config/noerd.php does not return an array — left untouched.</comment>');

            return;
        }

        $missing = self::configKeyDiff($stub, $current);
        $obsolete = self::configKeyDiff($current, $stub);

        if ($missing === [] && $obsolete === []) {
            $this->line('<comment>config/noerd.php already exists and declares every key — left untouched.</comment>');

            return;
        }

        if ($missing !== []) {
            $this->warn('config/noerd.php is missing new keys: ' . implode(', ', $missing));
        }

        if ($obsolete !== []) {
            $this->warn('config/noerd.php declares keys the package no longer reads: ' . implode(', ', $obsolete));
        }

        $this->line('<comment>config/noerd.php is never overwritten. Carry the changes over by hand (see config/noerd.php of the package).</comment>');
    }

    /**
     * The dot paths of $from that $other lacks. Only associative arrays are
     * walked — a list (or a map whose keys a host chooses itself, like the brand
     * presets) is a value, not a set of settings.
     *
     * @param  array<mixed>  $from
     * @param  array<mixed>  $other
     * @return list<string>
     */
    private static function configKeyDiff(array $from, array $other, string $prefix = ''): array
    {
        $diff = [];

        foreach ($from as $key => $value) {
            $path = $prefix . $key;

            if (! array_key_exists($key, $other)) {
                $diff[] = $path;

                continue;
            }

            if (is_array($value) && is_array($other[$key]) && ! array_is_list($value) && ! array_is_list($other[$key])
                && ! in_array($path, self::CONFIG_VALUE_MAPS, true)) {
                $diff = [...$diff, ...self::configKeyDiff($value, $other[$key], $path . '.')];
            }
        }

        return $diff;
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
