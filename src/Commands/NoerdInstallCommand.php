<?php

declare(strict_types=1);

namespace Noerd\Commands;

use Exception;
use Illuminate\Console\Command;

use function Laravel\Prompts\callout;
use function Laravel\Prompts\confirm;

use Laravel\Prompts\Elements\Link;
use Laravel\Prompts\Elements\NumberedList;
use Noerd\Commands\Concerns\PublishesNoerdContent;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;
use Noerd\Support\ModuleInstallContext;

class NoerdInstallCommand extends Command
{
    use PublishesNoerdContent;

    protected $signature = 'noerd:install
                            {--force : Overwrite existing files without asking}
                            {--migrate : Run migrations without asking (required to migrate in non-interactive runs)}
                            {--build : Run npm build without asking (required to build in non-interactive runs)}
                            {--demo : Install the demo app without asking (required to install it in non-interactive runs)}';

    protected $description = 'Install noerd: publish the setup app configs, config and assets, then migrate and create the first admin';

    public function handle(): int
    {
        $this->info('Installing noerd content...');

        try {
            if (! $this->publishNoerdContent()) {
                return self::FAILURE;
            }

            $this->runMigrationsAndSetupAdmin();
            $this->runNpmBuild();
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
     *
     * Installed for a module (`noerd:install-cms` on a fresh project), the question
     * is skipped unless that command was given --demo: someone installing a module
     * is setting up that module, not looking for the demo.
     */
    protected function installDemoApp(): void
    {
        if (ModuleInstallContext::isDependencyInstall() && ! $this->boolOption('demo')) {
            return;
        }

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
     * Create the first admin user of a fresh installation.
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

        if (NoerdUser::count() > 0) {
            $this->line('<comment>Users already exist, skipping. Promote one with: php artisan noerd:promote-admin {user_id}</comment>');

            return;
        }

        if (! $this->confirm('Would you like to create an admin user now?', true)) {
            $this->line('Skipping admin user creation. Create one later with: php artisan noerd:make-admin-user');

            return;
        }

        // noerd:make-admin-user owns the prompts and their validation — the first
        // user of an installation becomes super admin.
        $this->call('noerd:make-admin-user', ['--super-admin' => true]);
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

        // Installed for a module: the build belongs at the end of THAT run, once
        // the module's files are in place — otherwise node compiles a project the
        // module has not been added to yet, and asks the same question twice.
        if (ModuleInstallContext::isDependencyInstall()) {
            ModuleInstallContext::deferNpm(build: true);
            $this->line('<comment>The frontend build runs once the module installation has finished.</comment>');

            return;
        }

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
     *
     * Skipped while the base package is installed as part of a module install on
     * a fresh project (`noerd:install-cms` on a project without `config/noerd.php`):
     * that command closes with its own callout, and a second "ready" box halfway
     * through the run reads like the installation already finished.
     */
    protected function displayApplicationReady(): void
    {
        if (ModuleInstallContext::isDependencyInstall()) {
            return;
        }

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
