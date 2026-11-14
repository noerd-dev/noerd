<?php

declare(strict_types=1);

namespace Noerd\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;

use Noerd\Commands\Concerns\RequiresNoerdInstallation;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;

class NoerdDemoCommand extends Command
{
    use RequiresNoerdInstallation;

    protected $signature = 'noerd:demo
        {--force : Overwrite existing files}
        {--migrate : Run the demo migrations (required to migrate in non-interactive runs)}
        {--seed : Seed the demo data (required to seed in non-interactive runs)}
        {--tenants= : Assign the demo app without asking: "all" or tenant ids ("1,3") — required to assign in non-interactive runs}';

    protected $description = 'Install demo data (models, migrations, views, configs, routes) directly into the project';

    private string $demoDir;

    public function handle(): int
    {
        if (! $this->ensureNoerdInstalled()) {
            return self::FAILURE;
        }

        $this->demoDir = dirname(__DIR__, 2) . '/demo';

        $this->info('Installing noerd demo data...');

        $this->publishModels();
        $this->publishMigrations();
        $this->publishSeeder();
        $this->publishViews();
        $this->publishAppConfigs();
        $this->addRoutes();
        // The tables exist first — then the app is registered and assigned.
        $this->runMigration();
        $this->registerDemoApp();
        $this->seedDemoData();

        $this->newLine();
        $this->info('Noerd demo data installed successfully!');

        return self::SUCCESS;
    }

    private function publishModels(): void
    {
        $models = [
            'DemoCustomer.php',
            'DemoCategory.php',
            'DemoTag.php',
        ];

        foreach ($models as $model) {
            $source = $this->demoDir . '/Models/' . $model;
            $target = app_path('Models/' . $model);

            if (File::exists($source)) {
                $this->copyFile($source, $target, 'app/Models/' . $model);
            }
        }
    }

    private function publishMigrations(): void
    {
        $targetDir = database_path('migrations');

        // Order matters: tables referenced by foreign keys must be created first.
        $migrations = [
            'create_demo_categories_table',
            'create_demo_tags_table',
            'create_demo_customers_table',
            'create_demo_customer_demo_tag_table',
        ];

        $timestamp = (int) date('His');

        foreach ($migrations as $migration) {
            $existing = glob($targetDir . '/*_' . $migration . '.php');

            if (! empty($existing)) {
                $this->line("<comment>Migration for {$migration} already exists.</comment>");

                continue;
            }

            $source = $this->demoDir . '/migrations/' . $migration . '.php';
            if (! File::exists($source)) {
                continue;
            }

            $filename = date('Y_m_d_') . mb_str_pad((string) $timestamp, 6, '0', STR_PAD_LEFT) . '_' . $migration . '.php';
            $timestamp++;
            $target = $targetDir . '/' . $filename;

            File::copy($source, $target);
            $this->info("Published: database/migrations/{$filename}");
        }
    }

    private function publishViews(): void
    {
        $targetDir = resource_path('views/components');
        File::ensureDirectoryExists($targetDir);

        $views = [
            'demo-customers-list.blade.php',
            'demo-customer-detail.blade.php',
            'demo-categories-list.blade.php',
            'demo-category-detail.blade.php',
            'demo-tags-list.blade.php',
            'demo-tag-detail.blade.php',
        ];

        foreach ($views as $file) {
            $source = $this->demoDir . '/views/' . $file;
            $target = $targetDir . '/' . $file;

            if (File::exists($source)) {
                $this->copyFile($source, $target, 'resources/views/components/' . $file);
            }
        }
    }

    private function publishAppConfigs(): void
    {
        $this->publishAppConfigDir('demo', [
            'navigation.yml',
            'lists/demo-customers-list.yml',
            'details/demo-customer-detail.yml',
            'lists/demo-categories-list.yml',
            'details/demo-category-detail.yml',
            'lists/demo-tags-list.yml',
            'details/demo-tag-detail.yml',
        ]);
    }

    private function publishAppConfigDir(string $appName, array $files): void
    {
        $sourceDir = $this->demoDir . '/app-configs/' . $appName;
        $targetDir = base_path('app-configs/' . $appName);

        foreach ($files as $file) {
            $source = $sourceDir . '/' . $file;
            $target = $targetDir . '/' . $file;

            if (! File::exists($source)) {
                continue;
            }

            File::ensureDirectoryExists(dirname($target));
            $this->copyFile($source, $target, 'app-configs/' . $appName . '/' . $file);
        }
    }

    private function addRoutes(): void
    {
        $routeFile = base_path('routes/web.php');
        $content = File::get($routeFile);

        if (str_contains($content, "->name('demo-customers')")) {
            // Routes of an earlier demo: the views and configs now name demo.* routes.
            $this->warn('routes/web.php holds the demo routes under their earlier names (demo-customers, …).');
            $this->warn('Replace the "// Noerd Demo" block with the one of a fresh noerd:demo run (names demo.customers, demo.customer.detail, …).');

            return;
        }

        if (! str_contains($content, "->name('demo.customers')")) {
            $route = <<<'ROUTE'


// Noerd Demo
Route::group(['middleware' => ['noerd', 'app-access:demo']], function (): void {
    Route::livewire('demo-customers', 'demo-customers-list')->name('demo.customers');
    Route::livewire('demo-customer/{modelId}', 'demo-customer-detail')->name('demo.customer.detail');
    Route::livewire('demo-categories', 'demo-categories-list')->name('demo.categories');
    Route::livewire('demo-category/{modelId}', 'demo-category-detail')->name('demo.category.detail');
    Route::livewire('demo-tags', 'demo-tags-list')->name('demo.tags');
    Route::livewire('demo-tag/{modelId}', 'demo-tag-detail')->name('demo.tag.detail');
});
ROUTE;

            File::append($routeFile, $route);
            $this->info('Demo routes added to routes/web.php');
        } else {
            $this->line('<comment>Demo routes already exist in routes/web.php</comment>');
        }
    }

    private function registerDemoApp(): void
    {
        if (! Schema::hasTable('tenant_apps')) {
            $this->warn('The tenant_apps table does not exist yet — run php artisan migrate, then noerd:demo again to register the app.');

            return;
        }

        $app = TenantApp::firstOrCreate(
            ['name' => 'DEMO'],
            [
                'title' => 'Demo',
                'icon' => 'heroicon:outline:beaker',
                'route' => 'demo.customers',
                'is_active' => true,
            ],
        );

        if ($app->wasRecentlyCreated) {
            $this->info('Demo app registered in database.');
        } else {
            $this->line('<comment>Demo app already registered in database.</comment>');
        }

        $this->assignDemoApp($app);
    }

    /**
     * Assign the demo app — only to the tenants the run names: `--tenants`
     * (`all` or ids), or the selection an interactive run asks for. A
     * non-interactive run without the option assigns nothing.
     */
    private function assignDemoApp(TenantApp $app): void
    {
        $option = is_string($this->option('tenants')) ? mb_trim($this->option('tenants')) : '';

        if ($option === '' && ! $this->input->isInteractive()) {
            $this->line('<comment>Non-interactive run: the demo app was not assigned. Pass --tenants=all (or ids), or run: php artisan noerd:assign-apps-to-tenant --tenant-id={id} --apps=DEMO</comment>');

            return;
        }

        $tenants = Tenant::orderBy('name')->get();

        if ($tenants->isEmpty()) {
            return;
        }

        if ($option === '') {
            $selected = array_map('intval', multiselect(
                label: 'Which tenants should get the Demo app?',
                options: $tenants->pluck('name', 'id')->all(),
                default: $tenants->pluck('id')->all(),
                required: false,
            ));
        } elseif (mb_strtolower($option) === 'all') {
            $selected = $tenants->pluck('id')->map(fn($id): int => (int) $id)->all();
        } else {
            $ids = array_map('trim', explode(',', $option));

            if (array_filter($ids, static fn(string $id): bool => ! ctype_digit($id)) !== []) {
                $this->error('--tenants expects "all" or tenant ids separated by commas (e.g. --tenants=1,3).');

                return;
            }

            $selected = array_map('intval', $ids);
        }

        $assigned = 0;

        foreach ($tenants as $tenant) {
            if (in_array((int) $tenant->id, $selected, true)) {
                $tenant->assignApp($app);
                $assigned++;
            }
        }

        $this->info("{$app->title} app assigned to {$assigned} tenant(s).");
    }

    private function publishSeeder(): void
    {
        $source = $this->demoDir . '/seeders/DemoSeeder.php';
        $target = database_path('seeders/DemoSeeder.php');

        if (File::exists($source)) {
            $this->copyFile($source, $target, 'database/seeders/DemoSeeder.php');
        }
    }

    private function seedDemoData(): void
    {
        $seederPath = database_path('seeders/DemoSeeder.php');

        if (! File::exists($seederPath)) {
            return;
        }

        $shouldSeed = (bool) $this->option('seed')
            || ($this->input->isInteractive() && confirm('Would you like to seed demo data (sample customers, categories, tags)?', default: true));

        if (! $shouldSeed) {
            $this->line('<comment>Skipping demo seed data. Run manually: php artisan db:seed --class=DemoSeeder</comment>');

            return;
        }

        $this->call('db:seed', ['--class' => 'DemoSeeder', '--no-interaction' => true]);
    }

    private function runMigration(): void
    {
        $this->newLine();

        $shouldMigrate = (bool) $this->option('migrate')
            || ($this->input->isInteractive() && confirm('Would you like to run migrations now?', default: true));

        if (! $shouldMigrate) {
            $this->line('<comment>Skipping migrations. Run manually: php artisan migrate</comment>');

            return;
        }

        $this->call('migrate', $this->input->isInteractive() ? [] : ['--force' => true, '--no-interaction' => true]);
    }

    private function copyFile(string $source, string $target, string $displayPath): void
    {
        if (File::exists($target) && ! $this->option('force')) {
            $this->warn("File already exists: {$displayPath}");
            $this->line('Use --force to overwrite.');

            return;
        }

        File::ensureDirectoryExists(dirname($target));
        File::copy($source, $target);
        $this->info("Published: {$displayPath}");
    }
}
