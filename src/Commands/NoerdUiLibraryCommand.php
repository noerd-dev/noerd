<?php

namespace Noerd\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\multiselect;

use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;

class NoerdUiLibraryCommand extends Command
{
    protected $signature = 'noerd:ui-library {--force : Overwrite existing app registration}';

    protected $description = 'Install the UI Library — interactive showcase of all available UI components with live examples';

    public function handle(): int
    {
        $this->info('Installing UI Library...');

        $this->publishAppConfigs();
        $this->registerUiLibraryApp();

        $this->newLine();
        $this->info('UI Library installed successfully!');

        return self::SUCCESS;
    }

    private function publishAppConfigs(): void
    {
        $source = dirname(__DIR__, 2) . '/app-configs/ui-library/navigation.yml';
        $target = base_path('app-configs/ui-library/navigation.yml');

        if (File::exists($target) && ! $this->option('force')) {
            $this->line('<comment>Navigation config already exists.</comment>');

            return;
        }

        File::ensureDirectoryExists(dirname($target));
        File::copy($source, $target);
        $this->info('Published: app-configs/ui-library/navigation.yml');
    }

    private function registerUiLibraryApp(): void
    {
        $app = TenantApp::firstOrCreate(
            ['name' => 'UI-LIBRARY'],
            [
                'title' => 'UI Library',
                'icon' => 'noerd::icons.ui-library-app',
                'route' => 'ui-library.dashboard',
                'is_active' => true,
            ],
        );

        if ($app->wasRecentlyCreated) {
            $this->info('UI Library app registered in database.');
        } else {
            $this->line('<comment>UI Library app already registered in database.</comment>');
        }

        $this->assignAppToTenants($app);
    }

    private function assignAppToTenants(TenantApp $app): void
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found.');

            return;
        }

        if ($tenants->count() === 1) {
            $app->tenants()->syncWithoutDetaching([$tenants->first()->id]);
            $this->info("UI Library assigned to tenant '{$tenants->first()->name}'.");

            return;
        }

        $selected = multiselect(
            label: 'Which tenants should have access to the UI Library?',
            options: $tenants->pluck('name', 'id')->toArray(),
            default: $tenants->pluck('id')->toArray(),
        );

        $app->tenants()->syncWithoutDetaching($selected);
        $this->info('UI Library assigned to ' . count($selected) . ' tenant(s).');
    }
}
