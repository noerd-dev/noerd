<?php

namespace Noerd\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;

use function Laravel\Prompts\confirm;

class NoerdDemoCommand extends Command
{
    protected $signature = 'noerd:demo {--force : Overwrite existing files}';

    protected $description = 'Install demo data (model, migration, views, configs, route) directly into the project';

    private string $demoDir;

    public function handle(): int
    {
        $this->demoDir = dirname(__DIR__, 2) . '/demo';

        $this->info('Installing noerd demo data...');

        $this->publishModel();
        $this->publishMigration();
        $this->publishViews();
        $this->publishAppConfigs();
        $this->addRoute();
        $this->registerDemoApp();
        $this->runMigration();

        $this->newLine();
        $this->info('Noerd demo data installed successfully!');

        return self::SUCCESS;
    }

    private function publishModel(): void
    {
        $source = $this->demoDir . '/Models/DemoCustomer.php';
        $target = app_path('Models/DemoCustomer.php');

        $this->copyFile($source, $target, 'app/Models/DemoCustomer.php');
    }

    private function publishMigration(): void
    {
        $targetDir = database_path('migrations');
        $existing = glob($targetDir . '/*_create_demo_customers_table.php');

        if (! empty($existing)) {
            $this->line('<comment>Migration for demo_customers already exists.</comment>');

            return;
        }

        $source = $this->demoDir . '/migrations/create_demo_customers_table.php';
        $filename = date('Y_m_d_His') . '_create_demo_customers_table.php';
        $target = $targetDir . '/' . $filename;

        File::copy($source, $target);
        $this->info("Published: database/migrations/{$filename}");
    }

    private function publishViews(): void
    {
        $targetDir = resource_path('views/components');

        File::ensureDirectoryExists($targetDir);

        $files = [
            'demo-customers-list.blade.php',
            'demo-customer-detail.blade.php',
        ];

        foreach ($files as $file) {
            $source = $this->demoDir . '/views/' . $file;
            $target = $targetDir . '/' . $file;

            $this->copyFile($source, $target, 'resources/views/components/' . $file);
        }
    }

    private function publishAppConfigs(): void
    {
        $sourceDir = $this->demoDir . '/app-configs/demo';
        $targetDir = base_path('app-configs/demo');

        $files = [
            'navigation.yml',
            'lists/demo-customers-list.yml',
            'details/demo-customer-detail.yml',
        ];

        foreach ($files as $file) {
            $source = $sourceDir . '/' . $file;
            $target = $targetDir . '/' . $file;

            File::ensureDirectoryExists(dirname($target));
            $this->copyFile($source, $target, 'app-configs/demo/' . $file);
        }
    }

    private function addRoute(): void
    {
        $routeFile = base_path('routes/web.php');
        $content = File::get($routeFile);

        if (str_contains($content, 'demo-customers')) {
            $this->line('<comment>Demo route already exists in routes/web.php</comment>');

            return;
        }

        $route = <<<'ROUTE'


// Noerd Demo
Route::group(['middleware' => ['auth', 'verified', 'web']], function (): void {
    Route::livewire('demo-customers', 'demo-customers-list')->name('demo-customers');
    Route::livewire('demo-customer/{modelId}', 'demo-customer-detail')->name('demo-customer.detail');
});
ROUTE;

        File::append($routeFile, $route);
        $this->info('Demo routes added to routes/web.php');
    }

    private function registerDemoApp(): void
    {
        $app = TenantApp::firstOrCreate(
            ['name' => 'DEMO'],
            [
                'title' => 'Demo',
                'icon' => 'customer::icons.app',
                'route' => 'demo-customers',
                'is_active' => true,
            ],
        );

        if ($app->wasRecentlyCreated) {
            $this->info('Demo app registered in database.');
        } else {
            $this->line('<comment>Demo app already registered in database.</comment>');
        }

        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $app->tenants()->syncWithoutDetaching([$tenant->id]);
        }

        if ($tenants->isNotEmpty()) {
            $this->info("Demo app assigned to {$tenants->count()} tenant(s).");
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
