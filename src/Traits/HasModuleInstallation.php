<?php

namespace Noerd\Traits;

use Exception;
use Noerd\Models\TenantApp;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Yaml\Yaml;

trait HasModuleInstallation
{
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
     * Get the navigation source folder name.
     *
     * @deprecated No longer needed with new app-configs structure
     */
    protected function getNavigationSourceFolder(): string
    {
        return $this->getModuleKey();
    }

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

        if (! is_dir($targetDir)) {
            $this->error("Target not found: app-configs/{$this->getModuleKey()}/");
            $this->line('Run the install command first.');

            return 1;
        }

        $this->info("Updating {$this->getModuleName()} configurations...");
        $this->line('');

        try {
            // Copy lists
            $listsSource = $sourceDir . DIRECTORY_SEPARATOR . 'lists';
            $listsTarget = $targetDir . DIRECTORY_SEPARATOR . 'lists';
            if (is_dir($listsSource)) {
                $this->copyDirectoryContents($listsSource, $listsTarget);
            }

            // Copy details
            $detailsSource = $sourceDir . DIRECTORY_SEPARATOR . 'details';
            $detailsTarget = $targetDir . DIRECTORY_SEPARATOR . 'details';
            if (is_dir($detailsSource)) {
                $this->copyDirectoryContents($detailsSource, $detailsTarget);
            }

            // Copy additional subdirectories
            foreach ($this->getAdditionalSubdirectories() as $subdir) {
                $additionalSource = $sourceDir . DIRECTORY_SEPARATOR . $subdir;
                $additionalTarget = $targetDir . DIRECTORY_SEPARATOR . $subdir;
                if (is_dir($additionalSource)) {
                    $this->copyDirectoryContents($additionalSource, $additionalTarget);
                }
            }

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
     * Run the module installation process.
     */
    protected function runModuleInstallation(): int
    {
        // Ensure noerd:install has been run first
        if (! $this->ensureNoerdInstalled()) {
            return 1;
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
            // Copy lists
            $listsSource = $sourceDir . DIRECTORY_SEPARATOR . 'lists';
            $listsTarget = $targetDir . DIRECTORY_SEPARATOR . 'lists';
            if (is_dir($listsSource)) {
                $this->copyDirectoryContents($listsSource, $listsTarget);
            }

            // Copy details (formerly models/components)
            $detailsSource = $sourceDir . DIRECTORY_SEPARATOR . 'details';
            $detailsTarget = $targetDir . DIRECTORY_SEPARATOR . 'details';
            if (is_dir($detailsSource)) {
                $this->copyDirectoryContents($detailsSource, $detailsTarget);
            }

            // Copy additional subdirectories (e.g., collections, forms for CMS)
            foreach ($this->getAdditionalSubdirectories() as $subdir) {
                $additionalSource = $sourceDir . DIRECTORY_SEPARATOR . $subdir;
                $additionalTarget = $targetDir . DIRECTORY_SEPARATOR . $subdir;
                if (is_dir($additionalSource)) {
                    $this->copyDirectoryContents($additionalSource, $additionalTarget);
                }
            }

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
            if (! $this->confirm('Do you want to create a new migration anyway?', false)) {
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
            $this->line("<info>✓ TenantApp created via migration</info>");
        }

        return $exitCode === 0;
    }

    /**
     * Copy directory contents recursively.
     */
    protected function copyDirectoryContents(string $sourceDir, string $targetDir): void
    {
        if (! is_dir($targetDir)) {
            if (! mkdir($targetDir, 0755, true)) {
                throw new Exception("Failed to create directory: {$targetDir}");
            }
            $relativePath = str_replace(base_path('app-configs') . DIRECTORY_SEPARATOR, '', $targetDir);
            $this->line("<info>Created directory:</info> {$relativePath}");
            $this->installResults['created_dirs']++;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $sourcePath = $item->getPathname();
            $relativePath = mb_substr($sourcePath, mb_strlen($sourceDir) + 1);
            $targetPath = $targetDir . DIRECTORY_SEPARATOR . $relativePath;

            if ($item->isDir()) {
                if (! is_dir($targetPath)) {
                    if (! mkdir($targetPath, 0755, true)) {
                        throw new Exception("Failed to create directory: {$targetPath}");
                    }
                    $displayPath = str_replace(base_path('app-configs') . DIRECTORY_SEPARATOR, '', $targetPath);
                    $this->line("<info>Created directory:</info> {$displayPath}");
                    $this->installResults['created_dirs']++;
                }
            } else {
                $displayPath = str_replace(base_path('app-configs') . DIRECTORY_SEPARATOR, '', $targetPath);

                if (file_exists($targetPath)) {
                    if (! $this->option('force')) {
                        $choice = $this->choice(
                            "File already exists: {$displayPath}. What do you want to do?",
                            ['skip', 'overwrite', 'overwrite-all'],
                            'skip',
                        );

                        if ($choice === 'skip') {
                            $this->line("<comment>Skipped:</comment> {$displayPath}");
                            $this->installResults['skipped_files']++;

                            continue;
                        }
                        if ($choice === 'overwrite-all') {
                            $this->input->setOption('force', true);
                        }
                    }

                    $this->line("<comment>Overwriting:</comment> {$displayPath}");
                    $this->installResults['overwritten_files']++;
                } else {
                    $this->line("<info>Copying:</info> {$displayPath}");
                    $this->installResults['copied_files']++;
                }

                if (! copy($sourcePath, $targetPath)) {
                    throw new Exception("Failed to copy file: {$sourcePath} to {$targetPath}");
                }
            }
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
        $fromParts = explode('/', rtrim($from, '/'));
        $toParts = explode('/', rtrim($to, '/'));

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

    /**
     * Display the installation summary.
     */
    protected function displayInstallSummary(): void
    {
        $this->line('');
        $this->info('Installation Summary:');
        $this->table(
            ['Operation', 'Count'],
            [
                ['Directories created', $this->installResults['created_dirs']],
                ['Files copied', $this->installResults['copied_files']],
                ['Files overwritten', $this->installResults['overwritten_files']],
                ['Files skipped', $this->installResults['skipped_files']],
            ],
        );
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
            $this->line('Running npm run build...');
            $this->line('');

            $process = proc_open(
                'npm run build',
                [
                    0 => STDIN,
                    1 => STDOUT,
                    2 => STDERR,
                ],
                $pipes,
                base_path(),
            );

            if (is_resource($process)) {
                $exitCode = proc_close($process);

                $this->line('');
                if ($exitCode === 0) {
                    $this->info('Frontend assets compiled successfully!');
                } else {
                    $this->warn('npm run build finished with errors. You may need to run it manually.');
                }
            } else {
                $this->warn('Could not execute npm run build. Please run it manually.');
            }
        } else {
            $this->line('<comment>Skipping npm build. You can run it manually later with: npm run build</comment>');
        }
    }

    /**
     * Derive app key from title (replace umlauts, uppercase).
     */
    protected function deriveAppKey(string $title): string
    {
        return mb_strtoupper(str_replace(
            ['ä', 'ö', 'ü', 'ß', 'Ä', 'Ö', 'Ü', ' '],
            ['AE', 'OE', 'UE', 'SS', 'AE', 'OE', 'UE', '-'],
            $title,
        ));
    }

    /**
     * Derive app key from title (replace umlauts, lowercase, kebab-case).
     * Used for folder names in app-configs.
     */
    protected function deriveAppKeyLower(string $title): string
    {
        return mb_strtolower(str_replace(
            ['ä', 'ö', 'ü', 'ß', 'Ä', 'Ö', 'Ü', ' '],
            ['ae', 'oe', 'ue', 'ss', 'ae', 'oe', 'ue', '-'],
            $title,
        ));
    }
}
