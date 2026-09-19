<?php

declare(strict_types=1);

namespace Noerd\Traits;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

use function Laravel\Prompts\callout;

use Laravel\Prompts\Elements\Link;
use Laravel\Prompts\Elements\NumberedList;

use function Laravel\Prompts\multiselect;

use Noerd\Events\TenantAppAssigned;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;
use Noerd\Support\ModuleInstallContext;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Install/update contract of a module that IS a tenant app: on top of
 * InstallsNoerdModule it publishes the app's own app-configs folder with its
 * navigation, registers the tenant-app row and assigns the app to tenants.
 *
 *     public function handle(): int { return $this->runModuleInstallation(); }
 *     // update command (subclass): return $this->runModuleUpdate();
 */
trait HasModuleInstallation
{
    use InstallsNoerdModule;

    /** @var array{created_dirs: int, copied_files: int, skipped_files: int, overwritten_files: int} */
    private array $installResults = [
        'created_dirs' => 0,
        'copied_files' => 0,
        'skipped_files' => 0,
        'overwritten_files' => 0,
    ];

    private ?string $installedAppKey = null;

    private ?string $appTitle = null;

    /**
     * Get the module key (kebab-case).
     * Example: "business-hours"
     */
    abstract protected function getModuleKey(): string;

    /**
     * Get the default app title.
     * Example: "Business Hours"
     */
    abstract protected function getDefaultAppTitle(): string;

    /**
     * Get the app icon: a heroicon reference or a Blade icon view.
     * Example: "heroicon:outline:clock" or "business-hours::icons.app"
     */
    abstract protected function getAppIcon(): string;

    /**
     * Get the main app route.
     * Example: "business-hours.business-hours"
     */
    abstract protected function getAppRoute(): string;

    /**
     * Get the source directory for content files.
     * Example: dirname(__DIR__, 2) . '/app-configs/business-hours'
     */
    abstract protected function getSourceDir(): string;

    /**
     * Modules this one cannot work without, as tenant-app key => install command
     * — the CMS needs the media library for its image pickers:
     *
     *     return ['MEDIA' => 'noerd:install-media'];
     *
     * A module that is not installed yet is installed first, as a DEPENDENCY: it
     * publishes and registers, but asks no questions of its own. Its app is then
     * assigned along with this one (getRequiredAppKeys()).
     *
     * @return array<string, string>
     */
    protected function getRequiredModules(): array
    {
        return [];
    }

    /**
     * Tenant apps this module cannot work without. They are assigned to exactly
     * the tenants the module's own app was assigned to, in the SAME prompt: a
     * dependency never asks a question of its own. Assignment is additive only —
     * deselecting a tenant here never removes an app another module may equally
     * depend on. Defaults to the apps of getRequiredModules().
     *
     * @return array<string>
     */
    protected function getRequiredAppKeys(): array
    {
        return array_keys($this->getRequiredModules());
    }

    /**
     * The module root: the source dir is always {module}/app-configs/{key}.
     */
    protected function getModuleRoot(): string
    {
        return dirname($this->getSourceDir(), 2);
    }

    /**
     * Run another module's install command as a DEPENDENCY of this one: the
     * nested command publishes its configs and registers its app, but asks no
     * questions — this module assigns it through getRequiredAppKeys().
     *
     * @param  array<string, mixed>  $arguments
     */
    protected function installDependencyModule(string $command, array $arguments = []): int
    {
        return (int) ModuleInstallContext::asDependency(
            fn(): int => Artisan::call($command, $arguments, $this->output),
        );
    }

    /**
     * Run the module update process: republish the YAML configs and the module
     * resources, then the module's idempotent setup steps.
     */
    protected function runModuleUpdate(): int
    {
        if (! $this->ensureNoerdInstalled()) {
            return Command::FAILURE;
        }

        // Self-heal: when the app is registered (its tenant_apps row exists) but the
        // app-configs folder was never published — e.g. the app was added directly via
        // a migration/seeder, so runModuleInstallation() diverts here — the update
        // creates the folder instead of dead-ending with "run the install command".
        $targetDir = $this->prepareTargetDir();

        if ($targetDir === null) {
            return Command::FAILURE;
        }

        $this->info("Updating {$this->getModuleName()} configurations...");
        $this->line('');

        try {
            $this->publishAppConfigs($targetDir);

            $navSource = $this->getSourceDir() . DIRECTORY_SEPARATOR . 'navigation.yml';
            if (file_exists($navSource)) {
                $this->installResults[$this->publishFile(
                    $navSource,
                    $targetDir . DIRECTORY_SEPARATOR . 'navigation.yml',
                    $this->getModuleKey() . '/navigation.yml',
                )]++;
            }

            $this->publishModuleResources(update: true);
            $this->displayPublishSummary($this->installResults);
            $this->ensureModuleSetup();

            $this->line('');
            $this->info("{$this->getModuleName()} configurations updated!");

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error("Error updating {$this->getModuleName()}: " . $e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * The silent post-scaffold run started by noerd:make-app (`--scaffold`, when
     * the command declares the option): nothing is asked or printed except the
     * tenant assignment; migrations and the frontend build are not offered.
     */
    protected function isScaffoldInstall(): bool
    {
        return $this->input->hasOption('scaffold') && (bool) $this->option('scaffold');
    }

    /**
     * Run a step without any console output. Prompts must not fire inside — they
     * would render invisibly and wait for input.
     */
    protected function silently(callable $step): mixed
    {
        $verbosity = $this->output->getVerbosity();
        $this->output->setVerbosity(OutputInterface::VERBOSITY_QUIET);

        try {
            return $step();
        } finally {
            $this->output->setVerbosity($verbosity);
        }
    }

    /**
     * Run the module installation process.
     */
    protected function runModuleInstallation(): int
    {
        // An install command is a legitimate entry point into a fresh project.
        if (! $this->ensureNoerdInstalled()) {
            return Command::FAILURE;
        }

        if ($this->isScaffoldInstall()) {
            return $this->runScaffoldInstallation();
        }

        $this->installRequiredModules();

        // If the module is already installed, run as update instead to prevent
        // duplicate tenant app entries and overwriting customized navigation.
        $appKey = $this->deriveAppKey($this->getModuleKey());
        if ($this->tenantAppRegistered($appKey)) {
            $this->info("{$this->getModuleName()} is already installed. Running update instead...");
            $this->line('');

            $updateResult = $this->runModuleUpdate();

            // Tenant assignment must be offered on the update path too, otherwise
            // re-running install on an existing app would silently skip it.
            if ($updateResult === Command::SUCCESS) {
                $this->promptAppTenantAssignment($appKey);
                $this->displayModuleReady();
            }

            return $updateResult;
        }

        $this->info("Installing {$this->getModuleName()}...");
        $this->line('');

        // A dependency asks nothing: it is installed with its defaults.
        $this->appTitle = ModuleInstallContext::isDependencyInstall()
            ? $this->getDefaultAppTitle()
            : $this->ask('App title', $this->getDefaultAppTitle());

        $targetDir = $this->prepareTargetDir();

        if ($targetDir === null) {
            return Command::FAILURE;
        }

        try {
            $this->publishAppConfigs($targetDir);
            $this->installAsNewApp($this->getSourceDir(), $targetDir);
            $this->publishModuleResources(update: false);
            $this->displayPublishSummary($this->installResults);

            $this->line('');
            $this->info("{$this->getModuleName()} successfully installed!");

            // Ask to assign app to tenant (only if a new app was created)
            if ($this->installedAppKey) {
                $this->promptAppTenantAssignment($this->installedAppKey);
            }

            $this->askForMigration();
            $this->ensureModuleSetup();
            $this->askForNpmBuild();
            $this->displayModuleReady();

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error("Error installing {$this->getModuleName()}: " . $e->getMessage());
            $this->warnAboutDeferredNpm();

            return Command::FAILURE;
        }
    }

    /**
     * The post-scaffold install: publish configs, register the app and ask for the
     * tenant assignment — nothing else. Existing files are overwritten without
     * asking (the module was scaffolded a moment ago), the tenant-app migration is
     * published and run silently, `php artisan migrate` and `npm run build` are
     * left to the developer.
     */
    protected function runScaffoldInstallation(): int
    {
        $appKey = $this->deriveAppKey($this->getModuleKey());

        if ($this->input->hasOption('force')) {
            $this->input->setOption('force', true);
        }

        $this->appTitle = $this->getDefaultAppTitle();
        $targetDir = $this->prepareTargetDir(quiet: true);

        if ($targetDir === null) {
            return Command::FAILURE;
        }

        try {
            $this->silently(function () use ($targetDir, $appKey): void {
                $this->publishAppConfigs($targetDir);

                if (TenantApp::where('name', $appKey)->exists()) {
                    // Registered by an earlier attempt: refresh the navigation only.
                    $this->ensureTenantAppRegistered($appKey);
                } else {
                    $this->installAsNewApp($this->getSourceDir(), $targetDir);
                }

                $this->publishModuleResources(update: true);
            });
        } catch (Exception $e) {
            $this->error("Error installing {$this->getModuleName()}: " . $e->getMessage());

            return Command::FAILURE;
        }

        $this->assignAppToTenants($appKey, compact: true, alsoAssign: $this->requiredAppKeysFor($appKey));

        return Command::SUCCESS;
    }

    /**
     * Install every module of getRequiredModules() whose app is not registered yet.
     * A failing dependency is reported, never fatal: the required app is then
     * simply missing from the assignment (requiredAppKeysFor() says so).
     */
    protected function installRequiredModules(): void
    {
        foreach ($this->getRequiredModules() as $appKey => $command) {
            if ($this->tenantAppRegistered(mb_strtoupper($appKey))) {
                continue;
            }

            $this->line('');
            $this->info("{$this->getModuleName()} requires " . mb_strtoupper($appKey) . ", running {$command}...");

            try {
                $arguments = $this->input->hasOption('force') && $this->option('force') ? ['--force' => true] : [];

                if ($this->installDependencyModule($command, $arguments) !== Command::SUCCESS) {
                    $this->warn("{$command} did not complete.");
                }
            } catch (Exception $e) {
                $this->warn("Failed to run {$command}: " . $e->getMessage());
            }
        }
    }

    /**
     * Prompt the user to assign the app to tenants.
     *
     * Always offered — both on a fresh install and when the app already exists
     * (the update path) — so tenant assignment is never silently skipped.
     *
     * Except while this module is installed as a DEPENDENCY of another one: the
     * app that requires it names it in getRequiredModules() and assigns it in
     * its own prompt, so asking here would be a second question about tenants
     * the user never started an installation for.
     */
    protected function promptAppTenantAssignment(string $appKey): void
    {
        if (ModuleInstallContext::isDependencyInstall()) {
            $this->line('');
            $this->comment("Tenant assignment for '{$this->getModuleName()}' follows the app that requires it.");

            return;
        }

        $this->line('');
        if ($this->confirm('Would you like to assign the app to tenants now?', true)) {
            $this->assignAppToTenants($appKey, alsoAssign: $this->requiredAppKeysFor($appKey));
        }

        $this->line('');
        $this->comment('Note: On non-local systems (staging/production), tenant assignment');
        $this->comment('must be done manually after deployment using:');
        $this->line('  php artisan noerd:assign-apps-to-tenant');
    }

    /**
     * The required apps that are actually registered, without the module's own
     * app. A module may name an app whose package is not installed here — that
     * is a missing optional dependency, not a reason to fail the installation.
     *
     * @return array<string>
     */
    protected function requiredAppKeysFor(string $appKey): array
    {
        $keys = array_values(array_unique(array_filter(
            array_map(strtoupper(...), $this->getRequiredAppKeys()),
            static fn(string $key): bool => $key !== $appKey,
        )));

        if ($keys === []) {
            return [];
        }

        $registered = TenantApp::whereIn('name', $keys)->pluck('name')->all();

        foreach (array_diff($keys, $registered) as $missing) {
            $this->warn("Required app '{$missing}' is not installed — assign it manually once its module is installed.");
        }

        return array_values(array_intersect($keys, $registered));
    }

    /**
     * Assign a specific app to selected tenants
     *
     * @param  string  $appName  The name/key of the TenantApp (e.g., 'BUSINESS-HOURS')
     * @param  bool  $compact  Only the selection and the final count — no header, no per-tenant lines
     * @param  array<string>  $alsoAssign  Apps the module cannot work without (getRequiredAppKeys()).
     *                                     They follow the same selection, but ADDITIVELY: a tenant that
     *                                     loses this app keeps them, because another module may need them.
     */
    protected function assignAppToTenants(string $appName, bool $compact = false, array $alsoAssign = []): void
    {
        $app = TenantApp::where('name', $appName)->first();

        if (! $app) {
            $this->warn("App '{$appName}' not found in database.");

            return;
        }

        $tenants = Tenant::orderBy('name')->get();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found.');

            return;
        }

        $currentTenantIds = $app->tenants()->pluck('tenants.id')->map(fn($id): int => (int) $id)->all();

        $tenantChoices = [];
        foreach ($tenants as $tenant) {
            $status = in_array((int) $tenant->id, $currentTenantIds, true) ? ' [already assigned]' : '';
            $tenantChoices[$tenant->id] = "{$tenant->name}{$status}";
        }

        if (! $compact) {
            $this->line('');
            $this->info("Assign '{$app->title}' to tenants:");
            $this->comment('Use ↑/↓ to navigate, Space to select, Enter to confirm');
            $this->line('');
        }

        $selectedTenantIds = array_map('intval', multiselect(
            label: "Which tenants should '{$app->title}' be assigned to?",
            options: $tenantChoices,
            default: $tenants->pluck('id')->toArray(),
            required: false,
        ));

        foreach ($tenants as $tenant) {
            $isSelected = in_array((int) $tenant->id, $selectedTenantIds, true);
            $wasAssigned = in_array((int) $tenant->id, $currentTenantIds, true);

            if ($isSelected && ! $wasAssigned) {
                $tenant->tenantApps()->attach($app->id);
                TenantAppAssigned::dispatch($tenant->id, $appName);
                if (! $compact) {
                    $this->line("<info>✓ '{$app->title}' assigned to '{$tenant->name}'</info>");
                }
            } elseif (! $isSelected && $wasAssigned) {
                $tenant->tenantApps()->detach($app->id);
                if (! $compact) {
                    $this->line("<comment>✗ '{$app->title}' removed from '{$tenant->name}'</comment>");
                }
            }
        }

        $finalCount = $app->fresh()->tenants()->count();
        if (! $compact) {
            $this->line('');
        }
        $this->info("'{$app->title}' is now assigned to {$finalCount} tenant(s).");

        $this->assignRequiredApps($alsoAssign, $selectedTenantIds, $compact);
    }

    /**
     * Give every tenant that just got the app the apps it cannot work without.
     * Purely additive — a required app is never detached, it may be the reason
     * another installed module works.
     *
     * @param  array<string>  $appNames
     * @param  array<int|string>  $tenantIds
     */
    protected function assignRequiredApps(array $appNames, array $tenantIds, bool $compact = false): void
    {
        if ($appNames === [] || $tenantIds === []) {
            return;
        }

        $tenants = Tenant::whereIn('id', array_map('intval', $tenantIds))->orderBy('name')->get();

        foreach ($appNames as $appName) {
            $required = TenantApp::where('name', $appName)->first();

            if (! $required) {
                continue;
            }

            $assignedIds = $required->tenants()->pluck('tenants.id')->map('intval')->all();
            $attached = 0;

            foreach ($tenants as $tenant) {
                if (in_array((int) $tenant->id, $assignedIds, true)) {
                    continue;
                }

                $tenant->tenantApps()->attach($required->id);
                TenantAppAssigned::dispatch($tenant->id, $appName);
                $attached++;

                if (! $compact) {
                    $this->line("<info>✓ '{$required->title}' assigned to '{$tenant->name}' (required)</info>");
                }
            }

            if ($attached > 0) {
                $this->info("'{$required->title}' is required and was assigned to {$attached} tenant(s).");
            }
        }
    }

    /**
     * The closing "{Module} is ready" callout of an installation, pointing at the
     * app's own route — the place the user wants to go after installing it.
     *
     * Skipped for a module installed as a DEPENDENCY of another one: the run
     * belongs to the app the user asked for, and that one closes with its box.
     */
    protected function displayModuleReady(): void
    {
        if (ModuleInstallContext::isDependencyInstall()) {
            return;
        }

        callout("{$this->getModuleName()} is ready", [
            'You can start your local development using:',
            new NumberedList([
                'Run: php artisan dev',
                'Open: ' . new Link($this->appReadyUrl()) . ' and log in with your admin user',
            ]),
            'New to noerd? Check out the ' . new Link('https://noerd.dev', 'documentation') . '.',
            'Now go build an amazing business app!',
        ]);
    }

    /**
     * The URL the closing callout links to: the module's own app route, falling
     * back to the apps page when that route is not registered (the module's
     * service provider may not be booted yet) or needs parameters.
     */
    protected function appReadyUrl(): string
    {
        $appsUrl = mb_rtrim((string) config('app.url'), '/') . '/noerd-apps';
        $route = $this->getAppRoute();

        if (! Route::has($route)) {
            return $appsUrl;
        }

        try {
            return route($route);
        } catch (Exception) {
            return $appsUrl;
        }
    }

    /**
     * Install as a new standalone app.
     */
    protected function installAsNewApp(string $sourceDir, string $targetDir): void
    {
        // Copy navigation.yml first, before app registration which may abort early
        $navSource = $sourceDir . DIRECTORY_SEPARATOR . 'navigation.yml';
        $navTarget = $targetDir . DIRECTORY_SEPARATOR . 'navigation.yml';

        if (file_exists($navSource)) {
            $nav = Yaml::parse((string) file_get_contents($navSource));
            $nav[0]['name'] = $this->getModuleKey();
            $nav[0]['title'] = $this->appTitle;
            $nav[0]['route'] = $this->getModuleKey();
            file_put_contents($navTarget, Yaml::dump($nav, 10, 2));
            $this->line("<info>Copied navigation.yml to:</info> app-configs/{$this->getModuleKey()}/navigation.yml");
            $this->installResults['copied_files']++;
        }

        $appKey = $this->deriveAppKey($this->getModuleKey());

        $this->line("<comment>App key:</comment> {$appKey}");
        $this->line("<comment>App icon:</comment> {$this->getAppIcon()}");
        $this->line("<comment>Main route:</comment> {$this->getAppRoute()}");

        // Publish the (idempotent) migration so non-interactive deploys
        // (php artisan migrate) also register the app.
        $migrationFile = $this->publishMigration();
        if ($migrationFile) {
            $this->runSpecificMigration($migrationFile);
        }

        // Always ensure the row exists — restores it when a previous install
        // already recorded the migration as run and the row was later deleted
        // manually (an already-run migration never executes a second time).
        $this->ensureTenantAppRegistered($appKey);
    }

    /**
     * Guarantee the app's tenant_apps row exists. firstOrCreate keyed on `name`
     * makes this idempotent, so it restores a row that was manually deleted after
     * the registering migration had already been recorded as run, without ever
     * inserting a duplicate. Safe to call on every install.
     */
    protected function ensureTenantAppRegistered(string $appKey): void
    {
        TenantApp::firstOrCreate(
            ['name' => $appKey],
            [
                'title' => $this->appTitle ?? $this->getDefaultAppTitle(),
                'icon' => $this->getAppIcon(),
                'route' => $this->getAppRoute(),
                'is_active' => true,
            ],
        );

        $this->installedAppKey = $appKey;
    }

    /**
     * Get the path to the migration stub file.
     */
    protected function getMigrationStubPath(): string
    {
        return dirname($this->getSourceDir()) . '/stubs/add_' . $this->getModuleKey() . '_tenant_app.php.stub';
    }

    /**
     * Copy migration stub to main migrations directory with current timestamp.
     * Returns the filename of the created migration.
     */
    protected function publishMigration(): ?string
    {
        $stubPath = $this->getMigrationStubPath();

        if (! file_exists($stubPath)) {
            $this->warn("Migration stub not found: {$stubPath}");

            return null;
        }

        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_add_{$this->getModuleKey()}_tenant_app.php";
        $targetPath = database_path("migrations/{$filename}");

        // Check if migration already exists (by name pattern)
        $existingMigrations = glob(database_path("migrations/*_add_{$this->getModuleKey()}_tenant_app.php"));
        if (! empty($existingMigrations)) {
            $this->warn("Migration for {$this->getModuleName()} already exists.");
            // No prompt in a silent run (scaffold, dependency) — the existing one is reused.
            if ($this->isScaffoldInstall()
                || ModuleInstallContext::isDependencyInstall()
                || ! $this->confirm('Do you want to create a new migration anyway?', false)) {
                return basename($existingMigrations[0]);
            }
        }

        // Read stub and replace placeholders
        $content = file_get_contents($stubPath);
        $content = str_replace([
            '{{APP_TITLE}}',
            '{{APP_NAME}}',
            '{{APP_ICON}}',
            '{{APP_ROUTE}}',
        ], [
            $this->appTitle ?? $this->getDefaultAppTitle(),
            $this->deriveAppKey($this->getModuleKey()),
            $this->getAppIcon(),
            $this->getAppRoute(),
        ], $content);

        file_put_contents($targetPath, $content);

        $this->line("<info>✓ Migration published:</info> database/migrations/{$filename}");

        return $filename;
    }

    /**
     * Run only the specific migration file.
     */
    protected function runSpecificMigration(string $filename): bool
    {
        $this->line('');
        $this->info("Running migration: {$filename}");

        $exitCode = $this->call('migrate', [
            '--path' => "database/migrations/{$filename}",
            '--force' => true,
        ]);

        if ($exitCode === 0) {
            $this->line('<info>✓ TenantApp created via migration</info>');
        }

        return $exitCode === 0;
    }

    /**
     * The tenant-app key for a module key (umlauts transliterated, uppercase).
     */
    protected function deriveAppKey(string $moduleKey): string
    {
        return mb_strtoupper(str_replace(
            ['ä', 'ö', 'ü', 'ß', 'Ä', 'Ö', 'Ü', ' '],
            ['AE', 'OE', 'UE', 'SS', 'AE', 'OE', 'UE', '-'],
            $moduleKey,
        ));
    }

    /**
     * Whether the app row exists. The table is missing while the base package's
     * migrations have not run yet — that reads as "not registered", never a fatal.
     */
    private function tenantAppRegistered(string $appKey): bool
    {
        return Schema::hasTable('tenant_apps') && TenantApp::where('name', $appKey)->exists();
    }

    /**
     * The project's app-configs/{key} folder, created when missing. Null when the
     * module source is missing or the folder cannot be created (already reported).
     */
    private function prepareTargetDir(bool $quiet = false): ?string
    {
        $sourceDir = $this->getSourceDir();

        if (! is_dir($sourceDir)) {
            $this->error("Source directory not found: {$sourceDir}");

            return null;
        }

        $targetDir = base_path('app-configs/' . $this->getModuleKey());

        if (! is_dir($targetDir)) {
            if (! mkdir($targetDir, 0755, true) && ! is_dir($targetDir)) {
                $this->error("Failed to create target directory: app-configs/{$this->getModuleKey()}/");

                return null;
            }

            if (! $quiet) {
                $this->info("Created target directory: app-configs/{$this->getModuleKey()}/");
            }
        }

        return $targetDir;
    }

    /**
     * Publish the app's own YAML folders, accumulating the run's counters.
     */
    private function publishAppConfigs(string $targetDir): void
    {
        foreach ($this->copyConfigSubdirectories($this->getSourceDir(), $targetDir) as $key => $count) {
            $this->installResults[$key] += $count;
        }
    }
}
