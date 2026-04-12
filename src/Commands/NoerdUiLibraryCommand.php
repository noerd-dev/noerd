<?php

namespace Noerd\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;

class NoerdUiLibraryCommand extends Command
{
    protected $signature = 'noerd:ui-library {--force : Overwrite existing files}';

    protected $description = 'Install the UI Library — interactive showcase of all available UI components with live examples';

    private string $demoDir;

    public function handle(): int
    {
        $this->demoDir = dirname(__DIR__, 2) . '/demo';

        $this->info('Installing UI Library...');

        $this->publishViews();
        $this->publishAppConfigs();
        $this->addRoutes();
        $this->registerUiLibraryApp();

        $this->newLine();
        $this->info('UI Library installed successfully!');

        return self::SUCCESS;
    }

    private function publishViews(): void
    {
        $targetDir = resource_path('views/components');
        File::ensureDirectoryExists($targetDir);

        // Dashboard view
        $source = $this->demoDir . '/views/ui-library-dashboard.blade.php';
        $target = $targetDir . '/ui-library-dashboard.blade.php';

        if (File::exists($source)) {
            $this->copyFile($source, $target, 'resources/views/components/ui-library-dashboard.blade.php');
        }

        // Section views for UI Library dashboard
        $sectionsSourceDir = $this->demoDir . '/views/sections';
        $sectionsTargetDir = resource_path('views/ui-library-sections');
        File::ensureDirectoryExists($sectionsTargetDir);

        if (File::isDirectory($sectionsSourceDir)) {
            foreach (File::files($sectionsSourceDir) as $file) {
                $target = $sectionsTargetDir . '/' . $file->getFilename();
                $this->copyFile($file->getPathname(), $target, 'resources/views/ui-library-sections/' . $file->getFilename());
            }
        }

        // Icon view for UI Library app
        $iconSource = $this->demoDir . '/views/icons/ui-library-app.blade.php';
        $iconTargetDir = resource_path('views/components/icons');
        File::ensureDirectoryExists($iconTargetDir);

        if (File::exists($iconSource)) {
            $target = $iconTargetDir . '/ui-library-app.blade.php';
            $this->copyFile($iconSource, $target, 'resources/views/components/icons/ui-library-app.blade.php');
        }
    }

    private function publishAppConfigs(): void
    {
        $sourceDir = $this->demoDir . '/app-configs/ui-library';
        $targetDir = base_path('app-configs/ui-library');

        $files = ['navigation.yml'];

        foreach ($files as $file) {
            $source = $sourceDir . '/' . $file;
            $target = $targetDir . '/' . $file;

            if (! File::exists($source)) {
                continue;
            }

            File::ensureDirectoryExists(dirname($target));
            $this->copyFile($source, $target, 'app-configs/ui-library/' . $file);
        }
    }

    private function addRoutes(): void
    {
        $routeFile = base_path('routes/web.php');
        $content = File::get($routeFile);

        if (! str_contains($content, 'ui-library')) {
            $route = <<<'ROUTE'


// UI Library
Route::prefix('ui-library')->as('ui-library.')->middleware(['auth', 'verified', 'web', 'app-access:UI_LIBRARY'])->group(function (): void {
    Route::livewire('/dashboard', 'ui-library-dashboard')->name('dashboard');
});
ROUTE;

            File::append($routeFile, $route);
            $this->info('UI Library routes added to routes/web.php');
        } else {
            $this->line('<comment>UI Library routes already exist in routes/web.php</comment>');
        }
    }

    private function registerUiLibraryApp(): void
    {
        $app = TenantApp::firstOrCreate(
            ['name' => 'UI_LIBRARY'],
            [
                'title' => 'UI Library',
                'icon' => 'icons.ui-library-app',
                'route' => 'ui-library.dashboard',
                'is_active' => true,
            ],
        );

        if ($app->wasRecentlyCreated) {
            $this->info('UI Library app registered in database.');
        } else {
            $this->line('<comment>UI Library app already registered in database.</comment>');
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
