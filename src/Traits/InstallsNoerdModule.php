<?php

declare(strict_types=1);

namespace Noerd\Traits;

use Exception;
use Illuminate\Console\Command;
use Noerd\Commands\Concerns\PublishesConfigDirectory;
use Noerd\Commands\Concerns\PublishesModuleConfig;
use Noerd\Commands\Concerns\PublishesSkills;
use Noerd\Commands\Concerns\RegistersBoostPackage;
use Noerd\Commands\Concerns\RunsNpmBuild;
use Noerd\Commands\Concerns\WritesHostAppConfigs;
use Noerd\Support\ModuleInstallContext;
use ReflectionClass;

/**
 * The install/update contract of EVERY noerd module — with or without a tenant
 * app. A SUPPORT module (no navigation, no tenant app: payment, wallet,
 * notifications, …) uses this trait directly:
 *
 *     public function handle(): int { return $this->runSupportModuleInstallation(); }
 *     // update command (subclass): return $this->runSupportModuleUpdate();
 *
 * A TENANT-APP module uses HasModuleInstallation, which builds on this trait and
 * adds the app-configs navigation, the tenant-app row and the tenant assignment.
 *
 * What a module declares instead of coding it: getConfigFiles() (PHP config),
 * getAppConfigsDir() (YAML of screens that hang in other apps), a
 * {module}/app-configs/setup folder (setup-app YAML), publishModuleExtras()
 * (further files, before the migration) and ensureModuleSetup() (idempotent
 * steps after it: setup navigation, quick-menu button, seeds).
 */
trait InstallsNoerdModule
{
    use PublishesConfigDirectory;
    use PublishesModuleConfig;
    use PublishesSkills;
    use RegistersBoostPackage;
    use RequiresNoerdInstallation;
    use RunsNpmBuild;
    use WritesHostAppConfigs;

    /**
     * Get the module name for display purposes.
     * Example: "Business Hours"
     */
    abstract protected function getModuleName(): string;

    /**
     * The module root (composer.json, config/, resources/boost, skills/). By
     * convention a command lives in {module}/src/Commands/.
     */
    protected function getModuleRoot(): string
    {
        return dirname((string) (new ReflectionClass($this))->getFileName(), 3);
    }

    /**
     * YAML configs of screens that hang in OTHER apps' navigation (a support
     * module without a tenant app): the folder {module}/app-configs/{key},
     * published into the host's app-configs/{key}. Null = nothing to publish.
     */
    protected function getAppConfigsDir(): ?string
    {
        return null;
    }

    /**
     * Get additional subdirectories to copy (beyond lists, details, pages, settings).
     * Example: ['forms'] for CMS
     *
     * @return array<string>
     */
    protected function getAdditionalSubdirectories(): array
    {
        return [];
    }

    /**
     * Files the module publishes beyond the declared ones: a vendor migration
     * (PublishesAuditMigration), a patch to a host config file. Runs with the
     * other publishing steps — BEFORE the migration prompt, so a migration
     * published here is part of that run — on install AND update: it MUST be
     * idempotent and must not ask questions.
     */
    protected function publishModuleExtras(bool $update): void {}

    /**
     * The module's own setup steps: setup-navigation entries, quick-menu buttons,
     * dashboard widgets, seeds. Runs at the end of the installation (after the
     * migration prompt) AND on every update — it MUST be idempotent and must not
     * ask questions. Guard anything that needs a table the user may not have
     * migrated yet.
     */
    protected function ensureModuleSetup(): void {}

    /**
     * Install a support module: configs, agent guidelines, migrations.
     */
    protected function runSupportModuleInstallation(): int
    {
        if (! $this->ensureNoerdInstalled()) {
            return Command::FAILURE;
        }

        $this->info("Installing {$this->getModuleName()}...");
        $this->line('');

        try {
            $this->publishModuleResources(update: false);

            // A module without migrations of its own has nothing to offer here.
            if (glob($this->getModuleRoot() . '/database/migrations/*.php') !== []) {
                $this->askForMigration();
            }

            $this->ensureModuleSetup();
            $this->finishDeferredNpm();
        } catch (Exception $e) {
            $this->error("Error installing {$this->getModuleName()}: " . $e->getMessage());
            $this->warnAboutDeferredNpm();

            return Command::FAILURE;
        }

        $this->line('');
        $this->info("{$this->getModuleName()} successfully installed!");

        return Command::SUCCESS;
    }

    /**
     * Update a support module: republish what it ships, never migrate.
     */
    protected function runSupportModuleUpdate(): int
    {
        if (! $this->ensureNoerdInstalled()) {
            return Command::FAILURE;
        }

        $this->info("Updating {$this->getModuleName()}...");
        $this->line('');

        try {
            $this->publishModuleResources(update: true);
            $this->ensureModuleSetup();
        } catch (Exception $e) {
            $this->error("Error updating {$this->getModuleName()}: " . $e->getMessage());

            return Command::FAILURE;
        }

        $this->line('');
        $this->info("{$this->getModuleName()} updated!");

        return Command::SUCCESS;
    }

    /**
     * Everything a module publishes besides a tenant app's own app-configs:
     * PHP config, YAML of app-less screens, setup-app YAML, Claude skills and
     * the Boost registration.
     */
    protected function publishModuleResources(bool $update): void
    {
        $this->publishModuleConfigs($update);

        $appConfigsDir = $this->getAppConfigsDir();
        if ($appConfigsDir !== null && is_dir($appConfigsDir)) {
            $this->copyConfigSubdirectories($appConfigsDir, base_path('app-configs/' . basename($appConfigsDir)));
        }

        $this->publishSetupConfigs();
        $this->publishModuleExtras($update);
        $this->publishSkills(refreshCopies: $update);
        $this->registerBoostPackage($this->getModuleRoot());
    }

    /**
     * Copy every standard app-config subdirectory (lists, details, pages,
     * settings, plus getAdditionalSubdirectories()) into the project.
     *
     * @return array{created_dirs: int, copied_files: int, skipped_files: int, overwritten_files: int}
     */
    protected function copyConfigSubdirectories(string $sourceDir, string $targetDir): array
    {
        $totals = ['created_dirs' => 0, 'copied_files' => 0, 'skipped_files' => 0, 'overwritten_files' => 0];

        $subdirectories = array_merge(
            ['lists', 'details', 'pages', 'settings'],
            $this->getAdditionalSubdirectories(),
        );

        foreach ($subdirectories as $subdir) {
            $source = $sourceDir . DIRECTORY_SEPARATOR . $subdir;

            if (! is_dir($source)) {
                continue;
            }

            $results = $this->publishConfigDirectory($source, $targetDir . DIRECTORY_SEPARATOR . $subdir, base_path('app-configs'));

            foreach ($results as $key => $count) {
                $totals[$key] += $count;
            }
        }

        return $totals;
    }

    /**
     * Offer `php artisan migrate`. Never migrates implicitly: a non-interactive
     * run needs --migrate (when the command declares it), and a module installed
     * as a DEPENDENCY leaves the question to the module that requires it.
     */
    protected function askForMigration(): void
    {
        if (ModuleInstallContext::isDependencyInstall()) {
            return;
        }

        $this->line('');

        $migrate = $this->input->hasOption('migrate') && (bool) $this->option('migrate');

        if (! $migrate && ! $this->input->isInteractive()) {
            $this->line('<comment>Non-interactive run: skipping migrations. Run them with: php artisan migrate</comment>');

            return;
        }

        if (! $migrate) {
            $this->info('It is recommended to run migrations to ensure all database tables are up to date.');

            if (! $this->confirm('Would you like to run php artisan migrate now?', true)) {
                return;
            }
        }

        $this->call('migrate', $this->input->isInteractive() ? [] : ['--force' => true]);
    }

    /**
     * Offer `npm run build` — the closing step of a tenant-app installation.
     */
    protected function askForNpmBuild(): void
    {
        if (ModuleInstallContext::isDependencyInstall()) {
            return;
        }

        // A base installation that ran for this command handed its npm work over
        // — the packages are installed here, where the module's files exist.
        $this->installDeferredNpmPackages();
        ModuleInstallContext::takeDeferredNpmBuild();

        $this->line('');

        $build = $this->input->hasOption('build') && (bool) $this->option('build');

        if (! $build && ! $this->input->isInteractive()) {
            $this->line('<comment>Non-interactive run: skipping npm build. Run it with: npm run build</comment>');

            return;
        }

        if ($build || $this->confirm('Would you like to run "npm run build" to compile frontend assets?', true)) {
            $this->executeNpmBuild();
        } else {
            $this->line('<comment>Skipping npm build. You can run it manually later with: npm run build</comment>');
        }
    }

    /**
     * A support module has no frontend of its own, so it only touches npm when a
     * base installation that ran for it handed its npm work over.
     */
    protected function finishDeferredNpm(): void
    {
        if (ModuleInstallContext::isDependencyInstall() || ! ModuleInstallContext::hasDeferredNpm()) {
            return;
        }

        $this->askForNpmBuild();
    }

    /**
     * A base installation that ran for this command handed its npm work over —
     * say so when the installation died before it could be done, otherwise the
     * project is left with build tooling in package.json and no node_modules.
     */
    protected function warnAboutDeferredNpm(): void
    {
        if (ModuleInstallContext::isDependencyInstall() || ! ModuleInstallContext::hasDeferredNpm()) {
            return;
        }

        ModuleInstallContext::takeDeferredNpmPackages();
        ModuleInstallContext::takeDeferredNpmBuild();

        $this->warn('The frontend was not set up. Run it manually once the error is fixed:');
        $this->warn('  npm install && npm run build');
    }
}
