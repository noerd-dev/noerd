<?php

namespace Noerd\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

use function Laravel\Prompts\confirm;

use Noerd\Models\Tenant;
use Noerd\Models\User;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class NoerdInstallCommand extends Command
{
    protected $signature = 'noerd:install {--force : Overwrite existing files without asking}';

    protected $description = 'Install noerd content to the local content directory';

    public function handle()
    {
        $this->info('Installing noerd content...');

        $sourceDir = dirname(__DIR__, 2) . '/app-contents/setup';
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

            $this->info('Noerd content successfully installed!');

            return 0;
        } catch (Exception $e) {
            $this->error('Error installing noerd content: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Copy directory contents recursively
     */
    protected function copyDirectoryContents(string $sourceDir, string $targetDir): array
    {
        $results = [
            'created_dirs' => 0,
            'copied_files' => 0,
            'skipped_files' => 0,
            'overwritten_files' => 0,
        ];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $sourcePath = $item->getPathname();
            $relativePath = mb_substr($sourcePath, mb_strlen($sourceDir) + 1);
            $targetPath = $targetDir . DIRECTORY_SEPARATOR . $relativePath;

            if ($item->isDir()) {
                // Create directory if it doesn't exist
                if (!is_dir($targetPath)) {

                    if (!mkdir($targetPath, 0755, true)) {
                        throw new Exception("Failed to create directory: {$targetPath}");
                    }

                    $this->line("<info>Created directory:</info> {$relativePath}");
                    $results['created_dirs']++;
                }
            } else {
                // Check if file already exists
                if (file_exists($targetPath)) {
                    if (!$this->option('force')) {


                        $choice = $this->choice(
                            "File already exists: {$relativePath}. What do you want to do?",
                            ['skip', 'overwrite', 'overwrite-all'],
                            'skip',
                        );

                        if ($choice === 'skip') {
                            $this->line("<comment>Skipped:</comment> {$relativePath}");
                            $results['skipped_files']++;
                            continue;
                        }
                        if ($choice === 'overwrite-all') {
                            // Set force option for remaining files
                            $this->input->setOption('force', true);
                        }
                    }

                    $this->line("<comment>Overwriting:</comment> {$relativePath}");
                    $results['overwritten_files']++;
                } else {
                    $this->line("<info>Copying:</info> {$relativePath}");
                    $results['copied_files']++;
                }

                if (!copy($sourcePath, $targetPath)) {
                    throw new Exception("Failed to copy file: {$sourcePath} to {$targetPath}");
                }

            }
        }

        return $results;
    }

    protected function mergeResults(array $a, array $b): array
    {
        foreach (['created_dirs', 'copied_files', 'skipped_files', 'overwritten_files'] as $key) {
            $a[$key] = ($a[$key] ?? 0) + ($b[$key] ?? 0);
        }
        return $a;
    }

    /**
     * Setup frontend assets and configuration
     */
    protected function setupFrontendAssets(): void
    {
        $this->line('');
        $this->info('Setting up frontend assets...');

        try {
            // Update app.css
            $this->updateAppCss();

            // Install npm packages
            $this->installNpmPackages();

            // Create tailwind.config.js
            $this->createTailwindConfig();

            // Update auth configuration
            $this->updateAuthConfig();

            // Update Livewire component layout
            $this->updateLivewireConfig();

            // Update composer.json repositories
            $this->updateComposerRepositories();

            $this->line('<info>Frontend assets setup completed successfully.</info>');
        } catch (Exception $e) {
            $this->warn('Frontend assets setup failed: ' . $e->getMessage());
        }
    }

    /**
     * Update app.css with noerd styles
     */
    protected function updateAppCss(): void
    {
        $cssPath = base_path('resources/css/app.css');

        if (!file_exists($cssPath)) {
            $this->warn('app.css not found, skipping CSS updates.');
            return;
        }

        $cssContent = file_get_contents($cssPath);

        $noerdStyles = "
@source '../../vendor/noerd/noerd/resources/views/**/*.blade.php';
@source '../../vendor/noerd/modal/resources/views/**/*.blade.php';
@config '../../tailwind.config.js';
";

        // Check if noerd styles are already present
        if (!str_contains($cssContent, '@source \'../../vendor/noerd/noerd/resources/views/**/*.blade.php\';')) {
            file_put_contents($cssPath, $cssContent . $noerdStyles);
            $this->line('<info>Updated app.css with noerd styles.</info>');
        } else {
            $this->line('<comment>Noerd styles already present in app.css.</comment>');
        }
    }

    /**
     * Install required npm packages
     */
    protected function installNpmPackages(): void
    {
        $this->line('<comment>Installing npm packages...</comment>');

        $packages = [
            '@tailwindcss/forms@^0.5.2',
            'tailwind-scrollbar@^4.0.2',
            'dotenv@^16.4.7',
        ];

        $command = 'cd ' . base_path() . ' && npm install ' . implode(' ', $packages) . ' --save-dev';
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $this->warn('Failed to install npm packages. You may need to run the following manually:');
            $this->warn('npm install ' . implode(' ', $packages) . ' --save-dev');
        } else {
            $this->line('<info>NPM packages installed successfully.</info>');
        }
    }

    /**
     * Create tailwind.config.js
     */
    protected function createTailwindConfig(): void
    {
        $configPath = base_path('tailwind.config.js');

        if (file_exists($configPath) && !$this->option('force')) {
            if (!$this->confirm('tailwind.config.js already exists. Do you want to overwrite it?')) {
                $this->line('<comment>Skipped tailwind.config.js creation.</comment>');
                return;
            }
        }

        $configContent = "import defaultTheme from 'tailwindcss/defaultTheme'
import forms from '@tailwindcss/forms'

require('dotenv').config();

/** @type {import('tailwindcss').Config} */
export default {
    content: [

    ],

    safelist: [

    ],

    theme: {
        extend: {
            display: ['group-hover'],
            colors: {
                'brand-bg': process.env.VITE_BG_COLOR || '#f9f9f9',
                'brand-navi': process.env.VITE_BRAND_NAVI || '#fafafa',
                'brand-navi-hover': process.env.VITE_BRAND_NAVI_HOVER || '#f5f5f5',
                'brand-primary': process.env.VITE_BRAND_PRIMARY || '#000',
                'brand-border': process.env.VITE_BRAND_BORDER || '#000',
            },
        },
    },

    plugins: [forms, require('tailwind-scrollbar')],
}
";

        file_put_contents($configPath, $configContent);
        $this->line('<info>Created tailwind.config.js.</info>');
    }

    /**
     * Update .env file to set AUTH_MODEL to Noerd User model and install Breeze
     */
    protected function updateAuthConfig(): void
    {
        // Set AUTH_MODEL in .env
        $this->setAuthModelEnv();
    }

    /**
     * Set AUTH_MODEL in .env file
     */
    protected function setAuthModelEnv(): void
    {
        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            $this->warn('.env file not found, skipping AUTH_MODEL configuration.');
            return;
        }

        $envContent = file_get_contents($envPath);

        if (preg_match('/^AUTH_MODEL=/m', $envContent)) {
            $this->line('<comment>AUTH_MODEL already configured in .env file.</comment>');
            return;
        }

        $authModelLine = "\nAUTH_MODEL=Noerd\\Models\\User\n";

        if (file_put_contents($envPath, $envContent . $authModelLine) !== false) {
            $this->line('<info>Added AUTH_MODEL to .env file.</info>');
        } else {
            $this->warn('Failed to update .env file. Please manually add: AUTH_MODEL=Noerd\\Models\\User');
        }
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
        $this->line('');
        $this->info('Installation Summary:');
        $this->table(
            ['Operation', 'Count'],
            [
                ['Directories created', $results['created_dirs']],
                ['Files copied', $results['copied_files']],
                ['Files overwritten', $results['overwritten_files']],
                ['Files skipped', $results['skipped_files']],
            ],
        );
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
            if (!$this->option('force')) {
                if (!$this->confirm('config/noerd.php already exists. Do you want to overwrite it?', false)) {
                    $this->line('<comment>Skipped config/noerd.php publishing.</comment>');

                    return;
                }
            }
            $this->line('<comment>Overwriting config/noerd.php...</comment>');
        }

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

        $userCount = User::count();

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
            } elseif (User::where('email', $email)->exists()) {
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
        $user = User::create([
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
        $users = User::all();
        $adminUsers = $users->filter(fn(User $user) => $user->isAdmin());

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
        $options = $users->mapWithKeys(function (User $user) {
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
        $selectedUser = User::find($selectedUserId);

        if ($selectedUser->isAdmin()) {
            $this->line("<comment>User '{$selectedUser->name}' is already an admin.</comment>");
            return;
        }

        $this->makeUserAdmin($selectedUser);
    }

    /**
     * Make a user admin by calling the noerd:make-admin command
     */
    protected function makeUserAdmin(User $user): void
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

        $this->line('Running npm run build...');
        $this->newLine();

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

            $this->newLine();
            if ($exitCode === 0) {
                $this->info('Frontend assets compiled successfully!');
            } else {
                $this->warn('npm run build finished with errors. You may need to run it manually.');
            }
        } else {
            $this->warn('Could not execute npm run build. Please run it manually.');
        }
    }
}
