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
     * Get the middleware namespace.
     * Example: "Noerd\\BusinessHours\\Middleware"
     */
    abstract protected function getMiddlewareNamespace(): string;

    /**
     * Get the middleware class name.
     * Example: "BusinessHoursMiddleware"
     */
    abstract protected function getMiddlewareClass(): string;

    /**
     * Get the navigation source folder name.
     * Example: "business-hours"
     */
    abstract protected function getNavigationSourceFolder(): string;

    /**
     * Get the snippet title for duplicate checking.
     * Example: "Öffnungszeiten"
     */
    abstract protected function getSnippetTitle(): string;

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
        $targetDir = base_path('content');

        if (! is_dir($sourceDir)) {
            $this->error("Source directory not found: {$sourceDir}");

            return 1;
        }

        // Create target directory if it doesn't exist
        if (! is_dir($targetDir)) {
            if (! mkdir($targetDir, 0755, true)) {
                $this->error("Failed to create target directory: {$targetDir}");

                return 1;
            }
            $this->info("Created target directory: {$targetDir}");
        }

        try {
            // Copy lists
            $listsSource = $sourceDir . DIRECTORY_SEPARATOR . 'lists';
            $listsTarget = $targetDir . DIRECTORY_SEPARATOR . 'lists';
            if (is_dir($listsSource)) {
                $this->copyDirectoryContents($listsSource, $listsTarget);
            }

            // Copy components
            $componentsSource = $sourceDir . DIRECTORY_SEPARATOR . 'components';
            $componentsTarget = $targetDir . DIRECTORY_SEPARATOR . 'components';
            if (is_dir($componentsSource)) {
                $this->copyDirectoryContents($componentsSource, $componentsTarget);
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

            return 0;
        } catch (Exception $e) {
            $this->error("Error installing {$this->getModuleName()}: " . $e->getMessage());

            return 1;
        }
    }

    /**
     * Install as a new standalone app.
     */
    protected function installAsNewApp(string $sourceDir, string $targetDir): void
    {
        $this->line('');
        $this->info('New app configuration:');

        $appTitle = $this->ask('App name', $this->getDefaultAppTitle());

        // Automatically derive key from name (replace umlauts, uppercase)
        $appKey = $this->deriveAppKey($appTitle);

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
                'title' => $appTitle,
                'name' => $appKey,
                'icon' => $appIcon,
                'route' => $appRoute,
                'is_active' => true,
            ]);
            $this->line("<info>✓ TenantApp '{$appKey}' created in database</info>");
            $this->installedAppKey = $appKey;
        }

        // Generate Middleware
        $this->generateMiddleware($appKey);

        // Copy navigation
        $appKeyLower = mb_strtolower(str_replace('_', '-', $appKey));
        $navSource = $sourceDir . DIRECTORY_SEPARATOR . 'apps' . DIRECTORY_SEPARATOR . $this->getNavigationSourceFolder();
        $navTarget = $targetDir . DIRECTORY_SEPARATOR . 'apps' . DIRECTORY_SEPARATOR . $appKeyLower;

        if (is_dir($navSource)) {
            $this->copyDirectoryContents($navSource, $navTarget);

            // Update navigation.yml with correct app key
            $navFile = $navTarget . DIRECTORY_SEPARATOR . 'navigation.yml';
            if (file_exists($navFile)) {
                $navContent = file_get_contents($navFile);
                $nav = Yaml::parse($navContent);
                $nav[0]['name'] = $appKeyLower;
                $nav[0]['title'] = $appTitle;
                $nav[0]['route'] = $appKeyLower;
                file_put_contents($navFile, Yaml::dump($nav, 10, 2));
            }
        }
    }

    /**
     * Install to an existing app by adding navigation snippet.
     */
    protected function installToExistingApp(string $sourceDir, string $targetDir): void
    {
        // Get all active apps from database
        $apps = TenantApp::where('is_active', true)->get();

        if ($apps->isEmpty()) {
            $this->error('No active apps found in the database.');

            return;
        }

        // Build choice array
        $choices = [];
        foreach ($apps as $app) {
            $choices[$app->name] = $app->title . ' (' . $app->name . ')';
        }

        $selectedAppKey = $this->choice(
            "Select the app to add {$this->getModuleName()} to:",
            $choices,
        );

        $selectedApp = $apps->firstWhere('name', $selectedAppKey);
        $appKeyLower = mb_strtolower(str_replace('_', '-', $selectedAppKey));

        // Find navigation file
        $navFile = $targetDir . DIRECTORY_SEPARATOR . 'apps' . DIRECTORY_SEPARATOR . $appKeyLower . DIRECTORY_SEPARATOR . 'navigation.yml';

        if (! file_exists($navFile)) {
            $this->error("Navigation file not found: content/apps/{$appKeyLower}/navigation.yml");
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
                $this->warn("{$this->getModuleName()} navigation already exists in {$selectedApp->title} navigation.");

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

        $this->line("<info>✓ Navigation added to apps/{$appKeyLower}/navigation.yml</info>");
        $this->installResults['overwritten_files']++;
    }

    /**
     * Generate middleware from stub.
     */
    protected function generateMiddleware(string $appKey): void
    {
        $stubFile = $this->getMiddlewareStubPath();

        if (! file_exists($stubFile)) {
            $this->warn('Middleware stub not found, skipping middleware generation.');

            return;
        }

        $middlewareDir = $this->getMiddlewareDir();
        if (! is_dir($middlewareDir)) {
            mkdir($middlewareDir, 0755, true);
        }

        $middlewareFile = $middlewareDir . '/' . $this->getMiddlewareClass() . '.php';

        if (file_exists($middlewareFile) && ! $this->option('force')) {
            if (! $this->confirm('Middleware already exists. Overwrite?', false)) {
                $this->line('<comment>Middleware skipped</comment>');

                return;
            }
        }

        $stubContent = file_get_contents($stubFile);
        $middlewareContent = str_replace('{{APP_KEY}}', $appKey, $stubContent);

        file_put_contents($middlewareFile, $middlewareContent);
        $relativePath = str_replace(base_path() . '/', '', $middlewareFile);
        $this->line("<info>✓ Middleware created:</info> {$relativePath}");
    }

    /**
     * Get the path to the middleware stub file.
     */
    protected function getMiddlewareStubPath(): string
    {
        return __DIR__ . '/stubs/middleware.stub';
    }

    /**
     * Get the directory where middleware should be generated.
     */
    protected function getMiddlewareDir(): string
    {
        return base_path('app-modules/' . $this->getModuleKey() . '/src/Middleware');
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
            $relativePath = str_replace(base_path('content') . DIRECTORY_SEPARATOR, '', $targetDir);
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
                    $displayPath = str_replace(base_path('content') . DIRECTORY_SEPARATOR, '', $targetPath);
                    $this->line("<info>Created directory:</info> {$displayPath}");
                    $this->installResults['created_dirs']++;
                }
            } else {
                $displayPath = str_replace(base_path('content') . DIRECTORY_SEPARATOR, '', $targetPath);

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
        $this->info('Es wird empfohlen, die Migrationen auszuführen, um alle Datenbanktabellen zu aktualisieren.');

        if ($this->confirm('Möchten Sie jetzt php artisan migrate ausführen?', true)) {
            $this->call('migrate');
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
}
