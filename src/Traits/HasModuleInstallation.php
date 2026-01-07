<?php

namespace Noerd\Noerd\Traits;

use Exception;
use Noerd\Noerd\Models\TenantApp;
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
     * Example: base_path('app-modules/business-hours/content')
     */
    abstract protected function getSourceDir(): string;

    /**
     * Get the snippet title for duplicate checking.
     * Example: "Business Hours"
     */
    abstract protected function getSnippetTitle(): string;

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
     * Run the module installation process.
     */
    protected function runModuleInstallation(): int
    {
        // Ensure noerd:install has been run first
        if (! $this->ensureNoerdInstalled()) {
            return 1;
        }

        $this->info("Installing {$this->getModuleName()}...");
        $this->line('');

        $installationType = $this->choice(
            "How should {$this->getModuleName()} be installed?",
            [
                'new' => 'As a new standalone app',
                'existing' => 'Add to an existing app',
            ],
            'new',
        );

        $sourceDir = $this->getSourceDir();

        if (! is_dir($sourceDir)) {
            $this->error("Source directory not found: {$sourceDir}");

            return 1;
        }

        // Determine target directory based on installation type
        if ($installationType === 'new') {
            $this->line('');
            $this->info('New app configuration:');
            $this->appTitle = $this->ask('App title', $this->getDefaultAppTitle());
            $this->targetAppKey = $this->getModuleKey();
            $this->line("<comment>App folder:</comment> app-configs/{$this->targetAppKey}/");
        } else {
            // Get target from existing app selection
            $selectedApp = $this->selectExistingApp();
            if (! $selectedApp) {
                return 1;
            }
            $this->targetAppKey = mb_strtolower(str_replace('_', '-', $selectedApp->name));
            $this->appTitle = $selectedApp->title;
        }

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

            // Install navigation based on choice
            if ($installationType === 'new') {
                $this->installAsNewApp($sourceDir, $targetDir);
            } else {
                $this->installToExistingApp($sourceDir, $targetDir);
            }

            $this->displayInstallSummary();

            $this->line('');
            $this->info("{$this->getModuleName()} successfully installed!");

            // Ask to assign app to tenant (only if a new app was created)
            if ($this->installedAppKey) {
                $this->line('');
                if ($this->confirm('Would you like to assign the app to tenants now?', true)) {
                    $this->assignAppToTenants($this->installedAppKey);
                }
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
     * Select an existing app from the database.
     */
    protected function selectExistingApp(): ?TenantApp
    {
        $apps = TenantApp::where('is_active', true)->get();

        if ($apps->isEmpty()) {
            $this->error('No active apps found in the database.');

            return null;
        }

        $choices = [];
        foreach ($apps as $app) {
            $choices[$app->name] = $app->title . ' (' . $app->name . ')';
        }

        $selectedAppKey = $this->choice(
            "Select the app to add {$this->getModuleName()} to:",
            $choices,
        );

        return $apps->firstWhere('name', $selectedAppKey);
    }

    /**
     * Install as a new standalone app.
     */
    protected function installAsNewApp(string $sourceDir, string $targetDir): void
    {
        // App title was set in runModuleInstallation(), key is derived from module key
        $appKey = $this->deriveAppKey($this->getModuleKey());

        // Fixed values
        $appIcon = $this->getAppIcon();
        $appRoute = $this->getAppRoute();

        $this->line("<comment>App key:</comment> {$appKey}");
        $this->line("<comment>App icon:</comment> {$appIcon}");
        $this->line("<comment>Main route:</comment> {$appRoute}");

        // Check if app already exists
        $existingApp = TenantApp::where('name', $appKey)->first();
        if ($existingApp) {
            $this->warn("App '{$appKey}' already exists in the database.");
            if (! $this->confirm('Do you want to continue anyway?', false)) {
                return;
            }
        } else {
            // Create TenantApp entry
            TenantApp::create([
                'title' => $this->appTitle,
                'name' => $appKey,
                'icon' => $appIcon,
                'route' => $appRoute,
                'is_active' => true,
            ]);
            $this->line("<info>✓ TenantApp '{$appKey}' created in database</info>");
            $this->installedAppKey = $appKey;
        }

        // Copy navigation.yml to the target app-configs folder
        $navSource = $sourceDir . DIRECTORY_SEPARATOR . 'navigation.yml';
        $navTarget = $targetDir . DIRECTORY_SEPARATOR . 'navigation.yml';

        if (file_exists($navSource)) {
            $navContent = file_get_contents($navSource);
            $nav = Yaml::parse($navContent);
            $nav[0]['name'] = $this->targetAppKey;
            $nav[0]['title'] = $this->appTitle;
            $nav[0]['route'] = $this->targetAppKey;
            file_put_contents($navTarget, Yaml::dump($nav, 10, 2));
            $this->line("<info>Copied navigation.yml to:</info> app-configs/{$this->targetAppKey}/navigation.yml");
            $this->installResults['copied_files']++;
        }
    }

    /**
     * Install to an existing app by adding navigation snippet.
     */
    protected function installToExistingApp(string $sourceDir, string $targetDir): void
    {
        // Target app was already selected in runModuleInstallation()
        // Find navigation file in the target app folder
        $navFile = $targetDir . DIRECTORY_SEPARATOR . 'navigation.yml';

        if (! file_exists($navFile)) {
            $this->error("Navigation file not found: app-configs/{$this->targetAppKey}/navigation.yml");
            $this->line('Please make sure the app is installed correctly.');

            return;
        }

        // Read snippet file
        $snippetFile = $sourceDir . DIRECTORY_SEPARATOR . 'navigation-snippet.yml';
        if (! file_exists($snippetFile)) {
            $this->error('Navigation snippet not found.');

            return;
        }

        // Read current navigation
        $navContent = file_get_contents($navFile);
        $nav = Yaml::parse($navContent);

        // Check if module navigation already exists
        $snippetTitle = $this->getSnippetTitle();
        foreach ($nav[0]['block_menus'] ?? [] as $menu) {
            if (($menu['title'] ?? '') === $snippetTitle) {
                $this->warn("{$this->getModuleName()} navigation already exists in {$this->appTitle} navigation.");

                return;
            }
        }

        // Read snippet
        $snippetContent = file_get_contents($snippetFile);
        $snippet = Yaml::parse($snippetContent);

        // Add snippet to navigation
        $nav[0]['block_menus'][] = $snippet[0];

        // Write updated navigation
        $newContent = Yaml::dump($nav, 10, 2);
        file_put_contents($navFile, $newContent);

        $this->line("<info>✓ Navigation added to app-configs/{$this->targetAppKey}/navigation.yml</info>");
        $this->installResults['overwritten_files']++;
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
