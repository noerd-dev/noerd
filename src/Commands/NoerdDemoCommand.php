<?php

namespace Noerd\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\confirm;

use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;

class NoerdDemoCommand extends Command
{
    protected $signature = 'noerd:demo {--force : Overwrite existing files}';

    protected $description = 'Install demo data (models, migrations, views, configs, routes) directly into the project';

    private string $demoDir;

    public function handle(): int
    {
        $this->demoDir = dirname(__DIR__, 2) . '/demo';

        $this->info('Installing noerd demo data...');

        $this->publishModels();
        $this->publishMigrations();
        $this->publishViews();
        $this->publishAppConfigs();
        $this->addRoutes();
        $this->registerDemoApp();
        $this->runMigration();

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
            'create_demo_customers_table',
            'create_demo_categories_table',
            'create_demo_tags_table',
            'extend_demo_customers_table',
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

            $filename = date('Y_m_d_') . str_pad((string) $timestamp, 6, '0', STR_PAD_LEFT) . '_' . $migration . '.php';
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

        if (! str_contains($content, 'demo-customers')) {
            $route = <<<'ROUTE'


// Noerd Demo
Route::group(['middleware' => ['auth', 'verified', 'web']], function (): void {
    Route::livewire('demo-customers', 'demo-customers-list')->name('demo-customers');
    Route::livewire('demo-customer/{modelId}', 'demo-customer-detail')->name('demo-customer.detail');
    Route::livewire('demo-categories', 'demo-categories-list')->name('demo-categories');
    Route::livewire('demo-category/{modelId}', 'demo-category-detail')->name('demo-category.detail');
    Route::livewire('demo-tags', 'demo-tags-list')->name('demo-tags');
    Route::livewire('demo-tag/{modelId}', 'demo-tag-detail')->name('demo-tag.detail');
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
        $app = TenantApp::firstOrCreate(
            ['name' => 'DEMO'],
            [
                'title' => 'Demo',
                'icon' => 'noerd::icons.app',
                'route' => 'demo-customers',
                'is_active' => true,
            ],
        );

        if ($app->wasRecentlyCreated) {
            $this->info('Demo app registered in database.');
        } else {
            $this->line('<comment>Demo app already registered in database.</comment>');
        }

        $this->assignAppToAllTenants($app);
    }

    private function assignAppToAllTenants(TenantApp $app): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $app->tenants()->syncWithoutDetaching([$tenant->id]);
        }

        if ($tenants->isNotEmpty()) {
            $this->info("{$app->title} app assigned to {$tenants->count()} tenant(s).");
        }
    }

    private function runMigration(): void
    {
        $this->newLine();

        if (! confirm('Would you like to run migrations now?', default: true)) {
            $this->line('<comment>Skipping migrations. Run manually: php artisan migrate</comment>');

            return;
        }

        $this->call('migrate', ['--no-interaction' => true]);
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
