<?php

declare(strict_types=1);

namespace Noerd\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

use function Laravel\Prompts\callout;
use function Laravel\Prompts\confirm;

use Laravel\Prompts\Elements\Link;
use Laravel\Prompts\Elements\NumberedList;
use Noerd\Commands\Concerns\PublishesConfigDirectory;
use Noerd\Commands\Concerns\RunsNpmBuild;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;
use Noerd\Services\FrontendScaffolder;

class NoerdInstallCommand extends Command
{
    use PublishesConfigDirectory;
    use RunsNpmBuild;

    protected $signature = 'noerd:install
                            {--force : Overwrite existing files without asking}
                            {--migrate : Run migrations without asking (required to migrate in non-interactive runs)}
                            {--build : Run npm build without asking (required to build in non-interactive runs)}
                            {--demo : Install the demo app without asking (required to install it in non-interactive runs)}';

    protected $description = 'Install noerd: publish the setup app configs, config and assets, then migrate and create the first admin';

    public function handle(): int
    {
        $this->info('Installing noerd content...');

        $sourceDir = dirname(__DIR__, 2) . '/app-configs/setup';
        $targetDir = base_path('app-configs/setup');

        if (! is_dir($sourceDir)) {
            $this->error("Source directory not found: {$sourceDir}");
            return self::FAILURE;
        }

        // Create target directory if it doesn't exist
        if (! is_dir($targetDir)) {

            if (! mkdir($targetDir, 0755, true)) {
                $this->error("Failed to create target directory: {$targetDir}");
                return self::FAILURE;
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

            // Publish fonts + built Vite assets to public/vendor/noerd
            $this->publishNoerdAssets();

            // Run migrations and setup admin user
            $this->runMigrationsAndSetupAdmin();

            // Ask to run npm build
            $this->runNpmBuild();

            // Offer the demo app as the last installation step.
            $this->installDemoApp();

            $this->displayApplicationReady();

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error('Error installing noerd content: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Ask whether to install the demo app and run noerd:demo on confirmation.
     * Non-interactive runs never install the demo implicitly — a CI/deploy
     * invocation must opt in with --demo instead of inheriting the prompt default.
     */
    protected function installDemoApp(): void
    {
        $this->newLine();

        $shouldInstallDemo = $this->boolOption('demo');

        if (! $shouldInstallDemo && ! $this->input->isInteractive()) {
            $this->line('<comment>Non-interactive run: skipping the demo app. Pass --demo to install it.</comment>');

            return;
        }

        if (! $shouldInstallDemo) {
            $shouldInstallDemo = confirm(
                label: 'Would you like to install the Demo App?',
                default: true,
                hint: 'DemoCustomer with lists & details',
            );
        }

        if (! $shouldInstallDemo) {
            $this->line('<comment>Demo app will NOT be installed. You can run it later with: php artisan noerd:demo</comment>');

            return;
        }

        $this->call('noerd:demo', [
            '--force' => $this->option('force'),
            '--migrate' => $this->boolOption('migrate'),
            '--seed' => $this->boolOption('migrate'),
        ]);
    }

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

        if (! file_exists($configPath)) {
            $this->line('<comment>Publishing Livewire config file...</comment>');
            $this->call('livewire:config', ['--no-interaction' => true]);
        }

        if (! file_exists($configPath)) {
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

        if (! file_exists($composerPath)) {
            $this->warn('composer.json not found, skipping repositories update');
            return;
        }

        $composerContent = file_get_contents($composerPath);
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

        if (! is_dir($appModulesPath)) {
            if (! mkdir($appModulesPath, 0755, true)) {
                $this->warn('Failed to create app-modules directory');
                return;
            }
            $this->line('Created app-modules directory');
        } else {
            $this->line('<comment>app-modules directory already exists</comment>');
        }

        $gitkeepPath = $appModulesPath . DIRECTORY_SEPARATOR . '.gitkeep';

        if (! file_exists($gitkeepPath)) {
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

        if (! file_exists($phpunitPath)) {
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
     * Setup an admin user - either create a new one or promote an existing user.
     * Skipped in non-interactive runs: an admin needs prompted credentials, and
     * `noerd:create-admin` accepts them as options for scripted setups.
     */
    protected function setupAdminUser(): void
    {
        $this->newLine();
        $this->info('Admin User Setup');
        $this->line('================');

        if (! $this->input->isInteractive()) {
            $this->line('<comment>Non-interactive run: skipping admin setup. Create one with: php artisan noerd:create-admin --name= --email= --password=</comment>');
            return;
        }

        $userCount = NoerdUser::count();

        if ($userCount === 0) {
            $this->setupNewAdminUser();
        } else {
            $this->setupExistingAdminUser();
        }
    }

    /**
     * Create a new admin user when no users exist. Delegates to
     * noerd:create-admin, which owns the prompts and their validation —
     * the first user of an installation becomes super admin.
     */
    protected function setupNewAdminUser(): void
    {
        $this->line('<comment>No users found in the database.</comment>');

        if (! $this->confirm('Would you like to create an admin user now?', true)) {
            $this->line('Skipping admin user creation. You can create one later.');
            return;
        }

        $this->call('noerd:create-admin', ['--super-admin' => true]);
    }

    /**
     * Promote an existing user to admin
     */
    protected function setupExistingAdminUser(): void
    {
        $users = NoerdUser::all();
        $adminUsers = $users->filter(fn(NoerdUser $user) => $user->isAdminOfAnyTenant());

        if ($adminUsers->isNotEmpty()) {
            $this->line('<comment>Admin user(s) already exist:</comment>');
            foreach ($adminUsers as $admin) {
                $this->line("  - {$admin->name} ({$admin->email})");
            }

            if (! $this->confirm('Would you like to make another user an admin?', false)) {
                return;
            }
        } else {
            $this->line("<comment>Found {$users->count()} user(s) in the database, but none are admins.</comment>");

            if (! $this->confirm('Would you like to select a user to make admin?', true)) {
                $this->line('Skipping admin setup. You can do this later using: php artisan noerd:make-admin {user_id}');
                return;
            }
        }

        // Build options for choice prompt
        $options = $users->mapWithKeys(function (NoerdUser $user) {
            $adminTag = $user->isAdminOfAnyTenant() ? ' [ADMIN]' : '';
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

        if ($selectedUser->isAdminOfAnyTenant()) {
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
     * Migrations must be run before creating an admin user.
     * Non-interactive runs never migrate implicitly — opt in with --migrate.
     */
    protected function runMigrationsAndSetupAdmin(): void
    {
        $this->newLine();
        $this->info('Database Migration');
        $this->line('==================');
        $this->line('Running migrations is required before you can create an admin user.');
        $this->newLine();

        $shouldMigrate = $this->boolOption('migrate');

        if (! $shouldMigrate && ! $this->input->isInteractive()) {
            $this->line('<comment>Non-interactive run: skipping migrations. Pass --migrate to run them.</comment>');
            return;
        }

        if (! $shouldMigrate && ! confirm('Would you like to run "php artisan migrate" now?', default: true)) {
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
     * Ask to run npm build for frontend assets.
     * Non-interactive runs never build implicitly — opt in with --build.
     */
    protected function runNpmBuild(): void
    {
        $this->newLine();

        $shouldBuild = $this->boolOption('build');

        if (! $shouldBuild && ! $this->input->isInteractive()) {
            $this->line('<comment>Non-interactive run: skipping npm build. Pass --build to run it.</comment>');
            return;
        }

        if (! $shouldBuild && ! confirm('Would you like to run "npm run build" to compile frontend assets?', default: true)) {
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

        if (! function_exists('\Laravel\Prompts\callout')) {
            $this->newLine();
            $this->info('Application ready!');
            $this->line("Open: {$appsUrl} and log in with your admin user.");
            $this->line('New to noerd? Check out the documentation: https://noerd.dev');

            return;
        }

        callout('Application ready', [
            'You can start your local development using:',
            new NumberedList([
                'Run: php artisan dev',
                'Open: ' . new Link($appsUrl) . ' and log in with your admin user',
            ]),
            'New to noerd? Check out the ' . new Link('https://noerd.dev', 'documentation') . '.',
            'Now go build an amazing business app!',
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
