<?php

declare(strict_types=1);

namespace Noerd\Traits;

use Exception;
use Noerd\Models\TenantApp;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

trait HasModuleInstallation
{
    use \Noerd\Commands\Concerns\PublishesConfigDirectory;
    use \Noerd\Commands\Concerns\RunsNpmBuild;

    private array $installResults = [
        'created_dirs' => 0,
        'copied_files' => 0,
        'skipped_files' => 0,
        'overwritten_files' => 0,
    ];

    private ?string $installedAppKey = null;

    private ?string $targetAppKey = null;

    private ?string $appTitle = null;

    /**
     * Get the module name for display purposes.
     * Example: "Business Hours"
     */
    abstract protected function getModuleName(): string;

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
     * Get the app icon view path.
     * Example: "business-hours::icons.app"
     */
    abstract protected function getAppIcon(): string;

    /**
     * Get the main app route.
     * Example: "business-hours.business-hours"
     */
    abstract protected function getAppRoute(): string;

    /**
     * Get the source directory for content files.
     * Example: base_path('app-modules/business-hours/app-configs/business-hours')
     */
    abstract protected function getSourceDir(): string;

    /**
     * Get additional subdirectories to copy (beyond lists and details).
     * Example: ['collections', 'forms'] for CMS
     *
     * @return array<string>
     */
    protected function getAdditionalSubdirectories(): array
    {
        return [];
    }

    /**
     * Run the module update process (YML config files only).
     */
    protected function runModuleUpdate(): int
    {
        if (! $this->ensureNoerdInstalled()) {
            return 1;
        }

        $sourceDir = $this->getSourceDir();

        if (! is_dir($sourceDir)) {
            $this->error("Source directory not found: {$sourceDir}");

            return 1;
        }

        $targetDir = base_path('app-configs/' . $this->getModuleKey());

        // Self-heal: when the app is registered (its tenant_apps row exists) but the
        // app-configs folder was never published — e.g. the app was added directly via
        // a migration/seeder, so runModuleInstallation() diverts here — the update would
        // otherwise dead-end with "Run the install command first" while install keeps
        // diverting back to update. Create the folder and publish the configs into it.
        if (! is_dir($targetDir)) {
            if (! mkdir($targetDir, 0755, true) && ! is_dir($targetDir)) {
                $this->error("Failed to create target directory: app-configs/{$this->getModuleKey()}/");

                return 1;
            }
            $this->info("Created target directory: app-configs/{$this->getModuleKey()}/");
        }

        $this->info("Updating {$this->getModuleName()} configurations...");
        $this->line('');

        try {
            $this->copyConfigSubdirectories($sourceDir, $targetDir);

            // Copy navigation.yml
            $navSource = $sourceDir . DIRECTORY_SEPARATOR . 'navigation.yml';
            $navTarget = $targetDir . DIRECTORY_SEPARATOR . 'navigation.yml';
            if (file_exists($navSource)) {
                $displayPath = $this->getModuleKey() . '/navigation.yml';

                if (file_exists($navTarget)) {
                    if (! $this->option('force')) {
                        $choice = $this->choice(
                            "File already exists: {$displayPath}. What do you want to do?",
                            ['skip', 'overwrite', 'overwrite-all'],
                            'skip',
                        );

                        if ($choice === 'skip') {
                            $this->line("<comment>Skipped:</comment> {$displayPath}");
                            $this->installResults['skipped_files']++;
                        } else {
                            if ($choice === 'overwrite-all') {
                                $this->input->setOption('force', true);
                            }
                            copy($navSource, $navTarget);
                            $this->line("<comment>Overwriting:</comment> {$displayPath}");
                            $this->installResults['overwritten_files']++;
                        }
                    } else {
                        copy($navSource, $navTarget);
                        $this->line("<comment>Overwriting:</comment> {$displayPath}");
                        $this->installResults['overwritten_files']++;
                    }
                } else {
                    copy($navSource, $navTarget);
                    $this->line("<info>Copying:</info> {$displayPath}");
                    $this->installResults['copied_files']++;
                }
            }

            $this->publishSkills(refreshCopies: true);

            $this->displayInstallSummary();

            $this->line('');
            $this->info("{$this->getModuleName()} configurations updated!");

            return 0;
        } catch (Exception $e) {
            $this->error("Error updating {$this->getModuleName()}: " . $e->getMessage());

            return 1;
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
        // Ensure noerd:install has been run first
        if (! $this->ensureNoerdInstalled()) {
            return 1;
        }

        if ($this->isScaffoldInstall()) {
            return $this->runScaffoldInstallation();
        }

        // If the module is already installed, run as update instead to prevent
        // duplicate tenant app entries and overwriting customized navigation.
        $appKey = $this->deriveAppKey($this->getModuleKey());
        if (TenantApp::where('name', $appKey)->exists()) {
            $this->info("{$this->getModuleName()} is already installed. Running update instead...");
            $this->line('');

            $updateResult = $this->runModuleUpdate();

            // Tenant assignment must be offered on the update path too, otherwise
            // re-running install on an existing app would silently skip it.
            if ($updateResult === 0) {
                $this->promptAppTenantAssignment($appKey);
            }

            return $updateResult;
        }

        $this->info("Installing {$this->getModuleName()}...");
        $this->line('');

        $isHidden = $this->confirm(
            "Should {$this->getModuleName()} be installed as a hidden app (not shown in main navigation)?",
            false,
        );

        $sourceDir = $this->getSourceDir();

        if (! is_dir($sourceDir)) {
            $this->error("Source directory not found: {$sourceDir}");

            return 1;
        }

        $this->line('');
        $this->info('New app configuration:');
        $this->appTitle = $this->ask('App title', $this->getDefaultAppTitle());
        $this->targetAppKey = $this->getModuleKey();
        $this->line("<comment>App folder:</comment> app-configs/{$this->targetAppKey}/");

        $targetDir = base_path('app-configs/' . $this->targetAppKey);

        // Create target directory if it doesn't exist
        if (! is_dir($targetDir)) {
            if (! mkdir($targetDir, 0755, true)) {
                $this->error("Failed to create target directory: {$targetDir}");

                return 1;
            }
            $this->info("Created target directory: app-configs/{$this->targetAppKey}/");
        }

        try {
            $this->copyConfigSubdirectories($sourceDir, $targetDir);

            $this->installAsNewApp($sourceDir, $targetDir, $isHidden);

            $this->publishSkills(refreshCopies: false);

            $this->displayInstallSummary();

            $this->line('');
            $this->info("{$this->getModuleName()} successfully installed!");

            // Ask to assign app to tenant (only if a new app was created)
            if ($this->installedAppKey) {
                $this->promptAppTenantAssignment($this->installedAppKey);
            }

            // Ask to run migrations
            $this->askForMigration();

            // Ask to run npm build
            $this->askForNpmBuild();

            return 0;
        } catch (Exception $e) {
            $this->error("Error installing {$this->getModuleName()}: " . $e->getMessage());

            return 1;
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
        $sourceDir = $this->getSourceDir();

        if (! is_dir($sourceDir)) {
            $this->error("Source directory not found: {$sourceDir}");

            return 1;
        }

        if ($this->input->hasOption('force')) {
            $this->input->setOption('force', true);
        }

        $this->appTitle = $this->getDefaultAppTitle();
        $this->targetAppKey = $this->getModuleKey();
        $targetDir = base_path('app-configs/' . $this->targetAppKey);

        if (! is_dir($targetDir) && ! mkdir($targetDir, 0755, true) && ! is_dir($targetDir)) {
            $this->error("Failed to create target directory: {$targetDir}");

            return 1;
        }

        try {
            $this->silently(function () use ($sourceDir, $targetDir, $appKey): void {
                $this->copyConfigSubdirectories($sourceDir, $targetDir);

                if (TenantApp::where('name', $appKey)->exists()) {
                    // Registered by an earlier attempt: refresh the navigation only.
                    $this->ensureTenantAppRegistered($appKey);
                } else {
                    $this->installAsNewApp($sourceDir, $targetDir, false);
                }

                $this->publishSkills(refreshCopies: true);
            });
        } catch (Exception $e) {
            $this->error("Error installing {$this->getModuleName()}: " . $e->getMessage());

            return 1;
        }

        $this->assignAppToTenants($appKey, compact: true);

        return 0;
    }

    /**
     * Prompt the user to assign the app to tenants.
     *
     * Always offered — both on a fresh install and when the app already exists
     * (the update path) — so tenant assignment is never silently skipped.
     */
    protected function promptAppTenantAssignment(string $appKey): void
    {
        $this->line('');
        if ($this->confirm('Would you like to assign the app to tenants now?', true)) {
            $this->assignAppToTenants($appKey);
        }

        $this->line('');
        $this->comment('Note: On non-local systems (staging/production), tenant assignment');
        $this->comment('must be done manually after deployment using:');
        $this->line('  php artisan noerd:assign-apps-to-tenant');
    }

    /**
     * Install as a new standalone app.
     */
    protected function installAsNewApp(string $sourceDir, string $targetDir, bool $isHidden): void
    {
        // Copy navigation.yml first, before app registration which may abort early
        $navSource = $sourceDir . DIRECTORY_SEPARATOR . 'navigation.yml';
        $navTarget = $targetDir . DIRECTORY_SEPARATOR . 'navigation.yml';

        if (file_exists($navSource)) {
            $navContent = file_get_contents($navSource);
            $nav = Yaml::parse($navContent);
            $nav[0]['name'] = $this->targetAppKey;
            $nav[0]['title'] = $this->appTitle;
            $nav[0]['route'] = $this->targetAppKey;
            $nav[0]['hidden'] = $isHidden;
            file_put_contents($navTarget, Yaml::dump($nav, 10, 2));
            $this->line("<info>Copied navigation.yml to:</info> app-configs/{$this->targetAppKey}/navigation.yml");
            $this->installResults['copied_files']++;
        }

        // App title was set in runModuleInstallation(), key is derived from module key
        $appKey = $this->deriveAppKey($this->getModuleKey());

        // Fixed values
        $appIcon = $this->getAppIcon();
        $appRoute = $this->getAppRoute();

        $this->line("<comment>App key:</comment> {$appKey}");
        $this->line("<comment>App icon:</comment> {$appIcon}");
        $this->line("<comment>Main route:</comment> {$appRoute}");

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
            // No prompt in the silent scaffold run — it would render invisibly.
            if ($this->isScaffoldInstall() || ! $this->confirm('Do you want to create a new migration anyway?', false)) {
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
     * Copy every standard app-config subdirectory (lists, details, pages,
     * settings, plus the module's getAdditionalSubdirectories()) from the
     * module source into the project. Shared by install and update — the two
     * flows used to carry identical copies of this sequence.
     */
    protected function copyConfigSubdirectories(string $sourceDir, string $targetDir): void
    {
        $subdirectories = array_merge(
            ['lists', 'details', 'pages', 'settings'],
            $this->getAdditionalSubdirectories(),
        );

        foreach ($subdirectories as $subdir) {
            $source = $sourceDir . DIRECTORY_SEPARATOR . $subdir;
            if (is_dir($source)) {
                $this->copyDirectoryContents($source, $targetDir . DIRECTORY_SEPARATOR . $subdir);
            }
        }
    }

    /**
     * Copy directory contents recursively, accumulating the module-wide
     * install counters (see PublishesConfigDirectory).
     */
    protected function copyDirectoryContents(string $sourceDir, string $targetDir): void
    {
        $results = $this->publishConfigDirectory($sourceDir, $targetDir, base_path('app-configs'));

        foreach ($results as $key => $count) {
            $this->installResults[$key] += $count;
        }
    }

    /**
     * Publish all bundled Claude Code skills (every subdir of {module}/skills/)
     * into base_path('.claude/skills'). Prefers a relative symlink so the
     * skill auto-updates with the module; falls back to a recursive copy.
     *
     * When $refreshCopies is true (update mode), stale copies are replaced.
     * Symlinks are left alone (they reference source live).
     */
    protected function publishSkills(bool $refreshCopies = false): void
    {
        $skillsRoot = dirname($this->getSourceDir(), 2) . '/skills';

        if (! is_dir($skillsRoot)) {
            return;
        }

        $entries = glob($skillsRoot . '/*', GLOB_ONLYDIR) ?: [];
        if (empty($entries)) {
            return;
        }

        $targetSkillsDir = base_path('.claude/skills');

        if (! is_dir($targetSkillsDir) && ! mkdir($targetSkillsDir, 0755, true) && ! is_dir($targetSkillsDir)) {
            $this->warn('Could not create .claude/skills directory; skills not published.');

            return;
        }

        foreach ($entries as $sourcePath) {
            $resolved = realpath($sourcePath);
            if ($resolved === false) {
                continue;
            }
            $this->publishSingleSkill($resolved, $targetSkillsDir, basename($sourcePath), $refreshCopies);
        }
    }

    /**
     * Display the installation summary.
     */
    protected function displayInstallSummary(): void
    {
        $this->displayPublishSummary($this->installResults);
    }

    /**
     * Ensure a button exists in the global quick-menu config (app-configs/quick-menu.yml).
     * Rewrites any button still pointing at one of the $legacyComponents to the new
     * component name. An existing entry with the same component is REPLACED wholesale
     * (stale keys like the removed per-module `policy:` gates drop off on re-install),
     * except its `apps:` list, which is UNIONED with the new one — several modules may
     * contribute the same button (e.g. the booking family's customer select), and the
     * union keeps the result independent of the install order. Otherwise the button is
     * prepended.
     *
     * @param  array{component: string, app?: string, apps?: string[], policy?: string}  $button
     * @param  string[]  $legacyComponents
     */
    protected function ensureQuickMenuButton(array $button, array $legacyComponents = []): void
    {
        $configPath = base_path('app-configs/quick-menu.yml');

        $config = file_exists($configPath)
            ? (Yaml::parse(file_get_contents($configPath) ?: '') ?? [])
            : [];
        $buttons = $config['buttons'] ?? [];

        foreach ($buttons as $i => $existing) {
            if (in_array($existing['component'] ?? null, $legacyComponents, true)) {
                $buttons[$i]['component'] = $button['component'];
            }
        }

        $replaced = false;
        foreach ($buttons as $i => $existing) {
            if (($existing['component'] ?? null) !== ($button['component'] ?? null)) {
                continue;
            }

            $merged = $button;
            $apps = array_values(array_unique(array_merge(
                array_map('strval', (array) ($existing['apps'] ?? [])),
                array_map('strval', (array) ($button['apps'] ?? [])),
            )));
            if ($apps !== []) {
                $merged['apps'] = $apps;
            }

            $buttons[$i] = $merged;
            $replaced = true;
            break;
        }

        if (! $replaced) {
            $buttons = [$button, ...$buttons];
        }

        if ($buttons === ($config['buttons'] ?? [])) {
            $this->line('<comment>Quick-menu already contains the button.</comment>');

            return;
        }

        $dir = dirname($configPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $config['buttons'] = $buttons;
        file_put_contents($configPath, Yaml::dump($config, 10, 2));
        $this->line('<info>Quick-menu config updated:</info> app-configs/quick-menu.yml');
    }

    /**
     * Ensure a widget exists in the global dashboard-widgets config
     * (app-configs/dashboard-widgets.yml). Rewrites any widget still pointing at one of
     * the $legacyComponents to the new component name, then appends the widget if it is
     * not present yet. Matches on `component` only — an installation may re-tune
     * width/height without the installer duplicating or overwriting the entry — and
     * appends (unlike the quick-menu prepend) so the first-installed module keeps the
     * first slot on the dashboard. An existing entry's access keys are migrated: a
     * stale `policy:` (the removed per-module tenant gates would fail closed) is
     * dropped whenever the new widget declares `app:`/`apps:`, which are copied over.
     *
     * @param  array{component: string, app?: string, apps?: string[], policy?: string, width?: int, height?: int}  $widget
     * @param  string[]  $legacyComponents
     */
    protected function ensureDashboardWidget(array $widget, array $legacyComponents = []): void
    {
        $configPath = base_path('app-configs/dashboard-widgets.yml');

        $config = file_exists($configPath)
            ? (Yaml::parse(file_get_contents($configPath) ?: '') ?? [])
            : [];
        $widgets = $config['widgets'] ?? [];

        foreach ($widgets as $i => $existing) {
            if (in_array($existing['component'] ?? null, $legacyComponents, true)) {
                $widgets[$i]['component'] = $widget['component'];
            }
        }

        $present = false;
        foreach ($widgets as $i => $existing) {
            if (($existing['component'] ?? null) !== $widget['component']) {
                continue;
            }

            $present = true;

            if (isset($widget['app']) || isset($widget['apps'])) {
                unset($widgets[$i]['policy'], $widgets[$i]['app'], $widgets[$i]['apps']);
                foreach (['app', 'apps'] as $key) {
                    if (isset($widget[$key])) {
                        $widgets[$i][$key] = $widget[$key];
                    }
                }
            }

            break;
        }

        if (! $present) {
            $widgets[] = $widget;
        }

        if (array_values($widgets) === array_values($config['widgets'] ?? [])) {
            $this->line('<comment>Dashboard-widgets config already contains the widget.</comment>');

            return;
        }

        $widgets = array_values($widgets);

        $dir = dirname($configPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $config['widgets'] = $widgets;
        file_put_contents($configPath, Yaml::dump($config, 10, 2));
        $this->line('<info>Dashboard-widgets config updated:</info> app-configs/dashboard-widgets.yml');
    }

    /**
     * Ensure a navigation entry exists in a block of the project's setup navigation
     * (app-configs/setup/navigation.yml). Matches on the entry's `route`, so calling
     * this repeatedly never duplicates the entry. Creates the named block if absent.
     * An existing entry with the same route is REPLACED wholesale when it differs —
     * module-owned entries are owned by the module, so title changes and removed
     * keys (e.g. a dropped `config:` gate) propagate on re-install. The same applies
     * across blocks: an entry with that route found in ANY other block is removed, so
     * a module that regroups its entries MOVES them instead of duplicating them.
     *
     * Only the project copy is written — never the module's install template
     * (app-modules/noerd/app-configs/setup/navigation.yml): an entry naming a route
     * of an uninstalled module would otherwise ship to every installation. Stale
     * entries are additionally tolerated at render time — the sidebar skips entries
     * whose route is not registered.
     *
     * @param  array{title: string, route: string, heroicon?: string}  $entry
     */
    protected function ensureSetupNavigation(string $blockTitle, array $entry): void
    {
        $configPath = base_path('app-configs/setup/navigation.yml');

        if (! file_exists($configPath)) {
            $this->warn('app-configs/setup/navigation.yml not found; navigation entry was not added.');

            return;
        }

        $navigation = Yaml::parse(file_get_contents($configPath) ?: '') ?? [];

        $blockIndex = null;
        foreach ($navigation[0]['block_menus'] ?? [] as $i => $block) {
            if (($block['title'] ?? null) === $blockTitle) {
                $blockIndex = $i;
                break;
            }
        }

        if ($blockIndex === null) {
            $navigation[0]['block_menus'][] = ['title' => $blockTitle, 'navigations' => []];
            $blockIndex = array_key_last($navigation[0]['block_menus']);
        }

        $movedFromOtherBlock = false;
        foreach ($navigation[0]['block_menus'] as $i => $block) {
            if ($i === $blockIndex) {
                continue;
            }

            $navigations = $block['navigations'] ?? [];
            if (! is_array($navigations) || $navigations === []) {
                continue;
            }

            $remaining = array_values(array_filter(
                $navigations,
                fn(array $existing): bool => ($existing['route'] ?? null) !== $entry['route'],
            ));

            if (count($remaining) === count($navigations)) {
                continue;
            }

            $navigation[0]['block_menus'][$i]['navigations'] = $remaining;
            $movedFromOtherBlock = true;
        }

        foreach ($navigation[0]['block_menus'][$blockIndex]['navigations'] ?? [] as $index => $existing) {
            if (($existing['route'] ?? null) !== $entry['route']) {
                continue;
            }

            if ($existing === $entry && ! $movedFromOtherBlock) {
                $this->line("<comment>Setup navigation already contains:</comment> {$entry['title']}");

                return;
            }

            $navigation[0]['block_menus'][$blockIndex]['navigations'][$index] = $entry;
            file_put_contents($configPath, Yaml::dump($navigation, 10, 2));
            $this->line("<info>Setup navigation entry replaced:</info> {$blockTitle} → {$entry['title']}");

            return;
        }

        $navigation[0]['block_menus'][$blockIndex]['navigations'][] = $entry;
        file_put_contents($configPath, Yaml::dump($navigation, 10, 2));
        $this->line("<info>Setup navigation updated:</info> {$blockTitle} → {$entry['title']}");
    }

    /**
     * Ask the user if they want to run migrations.
     */
    protected function askForMigration(): void
    {
        $this->line('');
        $this->info('It is recommended to run migrations to ensure all database tables are up to date.');

        if ($this->confirm('Would you like to run php artisan migrate now?', true)) {
            $this->call('migrate');
        }
    }

    /**
     * Ask the user if they want to run npm build.
     */
    protected function askForNpmBuild(): void
    {
        $this->line('');

        if ($this->confirm('Would you like to run "npm run build" to compile frontend assets?', true)) {
            $this->executeNpmBuild();
        } else {
            $this->line('<comment>Skipping npm build. You can run it manually later with: npm run build</comment>');
        }
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

    private function publishSingleSkill(string $source, string $skillsDir, string $name, bool $refreshCopies): void
    {
        $target = $skillsDir . '/' . $name;

        if (is_link($target)) {
            $this->line("<comment>Claude skill already linked:</comment> .claude/skills/{$name}");

            return;
        }

        if (is_dir($target)) {
            if (! $refreshCopies) {
                $this->line("<comment>Claude skill already published:</comment> .claude/skills/{$name}");

                return;
            }
            $this->removeDirectoryTree($target);
            $this->line("<comment>Refreshing Claude skill:</comment> .claude/skills/{$name}");
        } elseif (file_exists($target)) {
            @unlink($target);
        }

        $relativeSource = $this->relativePath(from: $skillsDir, to: $source);

        if (@symlink($relativeSource, $target)) {
            $this->line("<info>Published Claude skill:</info> .claude/skills/{$name} → {$relativeSource}");

            return;
        }

        $this->warn("Symlink failed for skill '{$name}'; copying files instead.");
        $this->copyDirectoryTree($source, $target);
        $this->line("<info>Published Claude skill (copied):</info> .claude/skills/{$name}");
    }

    private function relativePath(string $from, string $to): string
    {
        $fromParts = explode('/', mb_rtrim($from, '/'));
        $toParts = explode('/', mb_rtrim($to, '/'));

        while ($fromParts && $toParts && $fromParts[0] === $toParts[0]) {
            array_shift($fromParts);
            array_shift($toParts);
        }

        return str_repeat('../', count($fromParts)) . implode('/', $toParts);
    }

    private function copyDirectoryTree(string $source, string $destination): void
    {
        if (! is_dir($destination) && ! mkdir($destination, 0755, true) && ! is_dir($destination)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $target = $destination . '/' . $iterator->getSubPathname();
            if ($item->isDir()) {
                if (! is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                copy($item->getPathname(), $target);
            }
        }
    }

    private function removeDirectoryTree(string $path): void
    {
        if (! is_dir($path) || is_link($path)) {
            @unlink($path);

            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $path . '/' . $entry;
            if (is_dir($full) && ! is_link($full)) {
                $this->removeDirectoryTree($full);
            } else {
                @unlink($full);
            }
        }
        @rmdir($path);
    }
}
