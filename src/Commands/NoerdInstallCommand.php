<?php

declare(strict_types=1);

namespace Noerd\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\callout;
use function Laravel\Prompts\confirm;

use Laravel\Prompts\Elements\Link;
use Laravel\Prompts\Elements\NumberedList;
use Noerd\Commands\Concerns\PublishesNoerdContent;
use Noerd\Commands\Concerns\RunsNpmBuild;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;

class NoerdInstallCommand extends Command
{
    use PublishesNoerdContent;
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

        if (! File::isDirectory($sourceDir)) {
            $this->error("Source directory not found: {$sourceDir}");
            return self::FAILURE;
        }

        // Create target directory if it doesn't exist
        if (! File::isDirectory($targetDir)) {
            if (! File::makeDirectory($targetDir, 0755, true)) {
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
     * Setup an admin user - either create a new one or promote an existing user.
     * Skipped in non-interactive runs: an admin needs prompted credentials, and
     * `noerd:make-admin-user` accepts them as options for scripted setups.
     */
    protected function setupAdminUser(): void
    {
        $this->newLine();
        $this->info('Admin User Setup');
        $this->line('================');

        if (! $this->input->isInteractive()) {
            $this->line('<comment>Non-interactive run: skipping admin setup. Create one with: php artisan noerd:make-admin-user --name= --email= --password=</comment>');
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
     * noerd:make-admin-user, which owns the prompts and their validation —
     * the first user of an installation becomes super admin.
     */
    protected function setupNewAdminUser(): void
    {
        $this->line('<comment>No users found in the database.</comment>');

        if (! $this->confirm('Would you like to create an admin user now?', true)) {
            $this->line('Skipping admin user creation. You can create one later.');
            return;
        }

        $this->call('noerd:make-admin-user', ['--super-admin' => true]);
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
                $this->line('Skipping admin setup. You can do this later using: php artisan noerd:promote-admin {user_id}');
                return;
            }
        }

        // Build options for choice prompt
        $options = $users->mapWithKeys(function (NoerdUser $user) {
            $adminTag = $user->isAdminOfAnyTenant() ? ' [ADMIN]' : '';
            return [$user->id => "{$user->name} ({$user->email}){$adminTag}"];
        })->toArray();

        $selectedLabel = $this->choice(
            'Select a user to make admin:',
            $options,
            null,
        );

        // Map the chosen label back to its user id. Strict comparison on
        // purpose: a loose array_search() matches the first label for any
        // non-string answer and would resolve to the wrong user.
        $selectedUserId = array_search($selectedLabel, $options, true);
        $selectedUser = $selectedUserId === false ? null : NoerdUser::find($selectedUserId);

        if (! $selectedUser) {
            $this->error('Could not resolve the selected user. Skipping admin setup.');
            return;
        }

        if ($selectedUser->isAdminOfAnyTenant()) {
            $this->line("<comment>User '{$selectedUser->name}' is already an admin.</comment>");
            return;
        }

        $this->makeUserAdmin($selectedUser);
    }

    /**
     * Make a user admin by calling the noerd:promote-admin command
     */
    protected function makeUserAdmin(NoerdUser $user): void
    {
        $this->line("Making user '{$user->name}' an admin...");

        $exitCode = $this->call('noerd:promote-admin', [
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
            $this->call('noerd:make-tenant');
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

    /**
     * Auto-assign all active apps to the default tenant (single-tenant mode).
     */
    private function autoAssignAllApps(): void
    {
        // noerd:make-tenant may have been aborted — there is nothing to assign to.
        $tenant = Tenant::first();

        if (! $tenant) {
            $this->line('<comment>No tenant was created, skipping the app assignment.</comment>');

            return;
        }

        $allAppIds = TenantApp::where('is_active', true)->pluck('id')->toArray();
        $tenant->tenantApps()->sync($allAppIds);
        $this->info("All apps auto-assigned to tenant '{$tenant->name}'.");
    }
}
