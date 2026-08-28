<?php

namespace Noerd\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

use function Laravel\Prompts\callout;
use function Laravel\Prompts\confirm;

use Laravel\Prompts\Elements\Link;
use Laravel\Prompts\Elements\NumberedList;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;
use Noerd\Services\FrontendScaffolder;

class NoerdInstallCommand extends Command
{
    use \Noerd\Commands\Concerns\PublishesConfigDirectory;
    use \Noerd\Commands\Concerns\RunsNpmBuild;

    protected $signature = 'noerd:install {--force : Overwrite existing files without asking}';

    protected $description = 'Install noerd content to the local content directory';

    public function handle()
    {
        $this->info('Installing noerd content...');

        $sourceDir = dirname(__DIR__, 2) . '/app-configs/setup';
        $targetDir = base_path('app-configs/setup');

        if (!is_dir($sourceDir)) {
            $this->error("Source directory not found: {$sourceDir}");
            return 1;
        }

        // Create target directory if it doesn't exist
        if (!is_dir($targetDir)) {

            if (!mkdir($targetDir, 0755, true)) {
                $this->error("Failed to create target directory: {$targetDir}");
                return 1;
            }

            $this->info("Created target directory: {$targetDir}");
        }

        try {
            $results = $this->copyDirectoryContents($sourceDir, $targetDir);

            $this->displaySummary($results);

            // Ensure app-modules directory exists
            $this->ensureAppModulesDirectory();

            // Update phpunit.xml with modules testsuite
            $this->updatePhpunitXml();

            // Publish noerd config file
            $this->publishNoerdConfig();

            // Setup frontend assets and configuration
            $this->setupFrontendAssets();

            // Run migrations and setup admin user
            $this->runMigrationsAndSetupAdmin();

            // Ask to run npm build
            $this->runNpmBuild();

            // Offer the demo app as the last installation step.
            $this->installDemoApp();

            $this->displayApplicationReady();

            return 0;
        } catch (Exception $e) {
            $this->error('Error installing noerd content: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Ask whether to install the demo app and run noerd:demo on confirmation
     */
    protected function installDemoApp(): void
    {
        $this->newLine();

        $shouldInstallDemo = confirm(
            label: 'Would you like to install the Demo App?',
            default: true,
            hint: 'DemoCustomer with lists & details',
        );

        if (! $shouldInstallDemo) {
            $this->line('<comment>Demo app will NOT be installed. You can run it later with: php artisan noerd:demo</comment>');

            return;
        }

        $this->call('noerd:demo', ['--force' => $this->option('force')]);
    }

    /**
     * Copy directory contents recursively (see PublishesConfigDirectory).
     */
    protected function copyDirectoryContents(string $sourceDir, string $targetDir): array
    {
        return $this->publishConfigDirectory($sourceDir, $targetDir);
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

            // Retire the obsolete tailwind.config.js brand bridge
            $this->migrateLegacyTailwindConfig($scaffolder);

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
        $result = \Illuminate\Support\Facades\Process::path(base_path())
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
     * Offer to remove the obsolete tailwind.config.js brand bridge
     *
     * Brand colors are registered as `--color-brand-*` in the package's noerd.css, so the generated
     * config and its `@config` line in app.css are obsolete. A host-customised config is
     * only reported — it is never removed automatically.
     */
    protected function migrateLegacyTailwindConfig(FrontendScaffolder $scaffolder): void
    {
        if (!$scaffolder->hasLegacyTailwindBridge()) {
            return;
        }

        if (!$scaffolder->legacyTailwindConfigIsUnmodified()) {
            $this->warn('tailwind.config.js looks customised and was left untouched.');
            $this->warn('Brand colors now ship as --color-brand-* in noerd.css; a `colors` block in your config overrides them at build time. See docs/brand.md.');

            return;
        }

        $this->line('');
        $this->line('<comment>Found the legacy noerd tailwind.config.js brand bridge.</comment>');
        $this->line('Brand colors now ship as --color-brand-* in noerd.css, which lets NOERD_BRAND take effect without a rebuild.');

        if (!$this->option('force') && !$this->confirm('Remove tailwind.config.js and its @config line from app.css?', true)) {
            $this->line('<comment>Kept tailwind.config.js.</comment>');

            return;
        }

        $scaffolder->removeLegacyTailwindBridge();

        $this->line('<info>Removed tailwind.config.js (backed up as tailwind.config.js.bak) and its @config line.</info>');
    }

    /**
     * Detect the installed Node version so the scaffolder can pin compatible build tooling
     */
    protected function detectNodeVersion(): ?string
    {
        exec('node -v 2>/dev/null', $output, $returnCode);

        if ($returnCode !== 0 || $output === []) {
            return null;
        }

        return mb_trim((string) $output[0]);
    }

    /**
     * Update Livewire config to use noerd layout
     */
    protected function updateLivewireConfig(): void
    {
        $configPath = base_path('config/livewire.php');

        if (!file_exists($configPath)) {
            $this->line('<comment>Publishing Livewire config file...</comment>');
            $this->call('livewire:config', ['--no-interaction' => true]);
        }

        if (!file_exists($configPath)) {
            $this->warn('config/livewire.php could not be published, skipping Livewire layout configuration.');

            return;
        }

        $configContent = file_get_contents($configPath);

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

        if (file_put_contents($configPath, $updated) !== false) {
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

        if (!file_exists($composerPath)) {
            $this->warn('composer.json not found, skipping repositories update');
            return;
        }

        $composerContent = file_get_contents($composerPath);
        $composerData = json_decode($composerContent, true);

        if (!$composerData) {
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

        if (file_put_contents($composerPath, $newContent) !== false) {
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
            dirname(__DIR__, 2) . '/stubs/noerd.php.stub', // app-modules/noerd/stubs
            base_path('vendor/noerd/noerd/stubs/noerd.php.stub'), // vendor installation
        ];

        $sourcePath = null;
        foreach ($possibleSources as $path) {
            if (file_exists($path)) {
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

        if (file_exists($targetPath)) {
            $this->refreshExistingNoerdConfig($sourcePath, $targetPath);

            return;
        }

        if (copy($sourcePath, $targetPath)) {
            $this->line('<info>Published config/noerd.php successfully.</info>');
        } else {
            $this->warn('Failed to publish config/noerd.php');
        }
    }

    /**
     * An existing config/noerd.php belongs to the host — never clobber it
     * silently. The stub is diffed against it: missing top-level keys are
     * reported (the documented contract is that noerd:update carries new keys
     * into existing installations), and an overwrite — via --force or an
     * explicit confirmation — first writes a one-generation .bak backup.
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

        if (!$this->option('force')) {
            if ($missing === []) {
                $this->line('<comment>config/noerd.php already exists and declares every stub key — left untouched.</comment>');

                return;
            }

            $this->warn('config/noerd.php is missing new top-level keys: ' . implode(', ', $missing));

            if (!$this->input->isInteractive()
                || !$this->confirm('Overwrite config/noerd.php with the current stub (a .bak backup is written)?', false)) {
                $this->line('<comment>Skipped config/noerd.php publishing. Add the missing keys manually (see stubs/noerd.php.stub).</comment>');

                return;
            }
        }

        @copy($targetPath, $targetPath . '.bak');
        $this->line('<comment>Overwriting config/noerd.php (backup: config/noerd.php.bak)...</comment>');

        if (copy($sourcePath, $targetPath)) {
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

        if (!is_dir($appModulesPath)) {
            if (!mkdir($appModulesPath, 0755, true)) {
                $this->warn('Failed to create app-modules directory');
                return;
            }
            $this->line('Created app-modules directory');
        } else {
            $this->line('<comment>app-modules directory already exists</comment>');
        }

        $gitkeepPath = $appModulesPath . DIRECTORY_SEPARATOR . '.gitkeep';

        if (!file_exists($gitkeepPath)) {
            if (file_put_contents($gitkeepPath, '') !== false) {
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

        if (!file_exists($phpunitPath)) {
            $this->warn('phpunit.xml not found, skipping phpunit configuration.');

            return;
        }

        $phpunitContent = file_get_contents($phpunitPath);

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

            if (file_put_contents($phpunitPath, $phpunitContent) !== false) {
                $this->line('<info>Added app-modules testsuite to phpunit.xml.</info>');
            } else {
                $this->warn('Failed to update phpunit.xml');
            }
        } else {
            $this->warn('Could not find </testsuites> tag in phpunit.xml. Please add the following testsuite manually:');
            $this->line($newTestsuite);
        }
    }

    /**
     * Setup an admin user - either create a new one or promote an existing user
     */
    protected function setupAdminUser(): void
    {
        $this->newLine();
        $this->info('Admin User Setup');
        $this->line('================');

        $userCount = NoerdUser::count();

        if ($userCount === 0) {
            $this->setupNewAdminUser();
        } else {
            $this->setupExistingAdminUser();
        }
    }

    /**
     * Create a new admin user when no users exist
     */
    protected function setupNewAdminUser(): void
    {
        $this->line('<comment>No users found in the database.</comment>');

        if (!$this->confirm('Would you like to create an admin user now?', true)) {
            $this->line('Skipping admin user creation. You can create one later.');
            return;
        }

        // Get name
        $name = null;
        while (empty($name)) {
            $name = $this->ask('What is the admin user\'s name?');
            if (empty($name)) {
                $this->error('Name is required.');
            }
        }

        // Get email
        $email = null;
        while (empty($email)) {
            $email = $this->ask('What is the admin user\'s email?');
            if (empty($email)) {
                $this->error('Email is required.');
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->error('Please enter a valid email address.');
                $email = null;
            } elseif (NoerdUser::where('email', $email)->exists()) {
                $this->error('A user with this email already exists.');
                $email = null;
            }
        }

        // Get password
        $passwordValue = null;
        while (empty($passwordValue)) {
            $passwordValue = $this->secret('Enter a password for the admin user (minimum 8 characters)');
            if (empty($passwordValue)) {
                $this->error('Password is required.');
            } elseif (mb_strlen($passwordValue) < 8) {
                $this->error('Password must be at least 8 characters.');
                $passwordValue = null;
            }
        }

        // Create the user
        $user = NoerdUser::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($passwordValue),
        ]);

        // First user becomes Super Admin
        $user->super_admin = true;
        $user->save();

        $this->newLine();
        $this->info("User '{$user->name}' created successfully as Super Admin.");

        // Make the user admin
        $this->makeUserAdmin($user);
    }

    /**
     * Promote an existing user to admin
     */
    protected function setupExistingAdminUser(): void
    {
        $users = NoerdUser::all();
        $adminUsers = $users->filter(fn(NoerdUser $user) => $user->isAdmin());

        if ($adminUsers->isNotEmpty()) {
            $this->line('<comment>Admin user(s) already exist:</comment>');
            foreach ($adminUsers as $admin) {
                $this->line("  - {$admin->name} ({$admin->email})");
            }

            if (!$this->confirm('Would you like to make another user an admin?', false)) {
                return;
            }
        } else {
            $this->line("<comment>Found {$users->count()} user(s) in the database, but none are admins.</comment>");

            if (!$this->confirm('Would you like to select a user to make admin?', true)) {
                $this->line('Skipping admin setup. You can do this later using: php artisan noerd:make-admin {user_id}');
                return;
            }
        }

        // Build options for choice prompt
        $options = $users->mapWithKeys(function (NoerdUser $user) {
            $adminTag = $user->isAdmin() ? ' [ADMIN]' : '';
            return [$user->id => "{$user->name} ({$user->email}){$adminTag}"];
        })->toArray();

        $selectedUserId = $this->choice(
            'Select a user to make admin:',
            $options,
            null,
        );

        // Find the actual user ID from the selected option
        $selectedUserId = array_search($selectedUserId, $options);
        $selectedUser = NoerdUser::find($selectedUserId);

        if ($selectedUser->isAdmin()) {
            $this->line("<comment>User '{$selectedUser->name}' is already an admin.</comment>");
            return;
        }

        $this->makeUserAdmin($selectedUser);
    }

    /**
     * Make a user admin by calling the noerd:make-admin command
     */
    protected function makeUserAdmin(NoerdUser $user): void
    {
        $this->line("Making user '{$user->name}' an admin...");

        $exitCode = $this->call('noerd:make-admin', [
            'user_id' => $user->id,
        ]);

        if ($exitCode === 0) {
            $this->newLine();
            $this->info("User '{$user->name}' is now an admin!");
        } else {
            $this->error("Failed to make user '{$user->name}' an admin.");
        }
    }

    /**
     * Run migrations and setup admin user
     * Migrations must be run before creating an admin user
     */
    protected function runMigrationsAndSetupAdmin(): void
    {
        $this->newLine();
        $this->info('Database Migration');
        $this->line('==================');
        $this->line('Running migrations is required before you can create an admin user.');
        $this->newLine();

        if (!confirm('Would you like to run "php artisan migrate" now?', default: true)) {
            $this->line('<comment>Skipping migrations. You can run them manually later with: php artisan migrate</comment>');
            $this->line('<comment>Note: You will need to run migrations before creating an admin user.</comment>');
            return;
        }

        $this->line('Running migrations...');
        $this->newLine();

        $this->call('migrate', ['--no-interaction' => true]);

        $this->newLine();

        // Create default tenant if none exist
        if (Tenant::count() === 0) {
            $this->call('noerd:create-tenant');
            $this->autoAssignAllApps();
        } else {
            $this->line('<comment>Tenant(s) already exist, skipping.</comment>');
        }

        $this->newLine();

        // Setup admin user
        $this->setupAdminUser();
    }

    /**
     * Ask to run npm build for frontend assets
     */
    protected function runNpmBuild(): void
    {
        $this->newLine();

        if (!confirm('Would you like to run "npm run build" to compile frontend assets?', default: true)) {
            $this->line('<comment>Skipping npm build. You can run it manually later with: npm run build</comment>');
            return;
        }

        $this->executeNpmBuild();
    }

    /**
     * Display the closing "Application ready" callout with the next steps.
     */
    protected function displayApplicationReady(): void
    {
        $url = mb_rtrim((string) config('app.url'), '/');
        $appsUrl = $url . '/noerd-apps';

        if (!function_exists('\Laravel\Prompts\callout')) {
            $this->newLine();
            $this->info('Application ready!');
            $this->line("Open: {$appsUrl} and log in with your admin user.");
            $this->line('New to noerd? Check out the documentation: https://noerd.dev');

            return;
        }

        callout('Application ready', [
            'You can start your local development using:',
            new NumberedList([
                'Run: composer run dev',
                'Open: ' . new Link($appsUrl) . ' and log in with your admin user',
            ]),
            'New to noerd? Check out the ' . new Link('https://noerd.dev', 'documentation') . '.',
            'Build something amazing!',
        ]);
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

    /**
     * Auto-assign all active apps to the default tenant (single-tenant mode).
     */
    private function autoAssignAllApps(): void
    {
        $tenant = Tenant::first();
        $allAppIds = TenantApp::where('is_active', true)->pluck('id')->toArray();
        $tenant->tenantApps()->sync($allAppIds);
        $this->info("All apps auto-assigned to tenant '{$tenant->name}'.");
    }
}
