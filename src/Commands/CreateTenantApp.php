<?php

namespace Noerd\Noerd\Commands;

use Exception;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\search;

use Noerd\Noerd\Models\Tenant;
use Noerd\Noerd\Models\TenantApp;

class CreateTenantApp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'noerd:create-app
                            {--title= : The display title of the app}
                            {--name= : The unique name identifier of the app}
                            {--icon= : The icon identifier for the app}
                            {--route= : The main route name for the app}
                            {--active=1 : Whether the app is active (1 or 0)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new app that can be assigned to tenants';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $title = $this->option('title');
        $name = $this->option('name');
        $icon = $this->option('icon');
        $route = $this->option('route');
        $active = (bool) $this->option('active');

        // Interactive mode if no options provided and not in test environment
        if ((!$title || !$name || !$icon || !$route) && !app()->runningUnitTests()) {
            $this->info('Creating a new tenant app...');
            $this->newLine();

            $title = $title ?: $this->ask('App Title (display name)');
            $name = $this->normalizeAppName($name ?: $this->ask('App Name (unique identifier, e.g., CMS, MEDIA)'));
            $icon = $icon ?: $this->askForIcon();
            $route = $route ?: $this->ask('App Route (main route name, e.g., cms.pages, media.dashboard)');
        }

        // Normalize name if provided via option
        if ($name) {
            $name = $this->normalizeAppName($name);
        }

        // Validate required fields
        if (!$title || !$name || !$icon || !$route) {
            $this->error('All fields (title, name, icon, route) are required.');
            return self::FAILURE;
        }

        // Validate name format (uppercase, no spaces) after normalization
        if (!preg_match('/^[A-Z_]+$/', $name)) {
            $this->error('App name must contain only uppercase letters and underscores (e.g., CMS, MEDIA, MY_APP).');
            return self::FAILURE;
        }

        // Check if app with this name already exists
        if (TenantApp::where('name', $name)->exists()) {
            $this->error("App with name '{$name}' already exists.");
            return self::FAILURE;
        }

        // Create the tenant app
        try {
            $tenantApp = TenantApp::create([
                'title' => $title,
                'name' => $name,
                'icon' => $icon,
                'route' => $route,
                'is_active' => $active,
            ]);

            $this->newLine();
            $this->info("✅ Tenant app created successfully!");

            $this->table(['Field', 'Value'], [
                ['ID', $tenantApp->id],
                ['Title', $tenantApp->title],
                ['Name', $tenantApp->name],
                ['Icon', $tenantApp->icon],
                ['Route', $tenantApp->route],
                ['Active', $tenantApp->is_active ? 'Yes' : 'No'],
                ['Created', $tenantApp->created_at->format('Y-m-d H:i:s')],
            ]);

            if (!app()->runningUnitTests()) {
                $this->askToAssignTenants($tenantApp);
            } else {
                $this->newLine();
                $this->comment('Run "php artisan noerd:assign-apps-to-tenant" to assign this app to a tenant.');
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error("Failed to create tenant app: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    protected function askForIcon(): string
    {
        $iconsPath = base_path('vendor/wireui/heroicons/src/views/components/outline');
        $icons = collect(scandir($iconsPath))
            ->filter(fn($file) => str_ends_with($file, '.blade.php'))
            ->map(fn($file) => str_replace('.blade.php', '', $file))
            ->values()
            ->all();

        return search(
            label: 'Search for a Heroicon',
            options: fn(string $search) => collect($icons)
                ->filter(fn($icon) => empty($search) || str_contains($icon, $search))
                ->mapWithKeys(fn($icon) => [
                    "heroicon:outline:{$icon}" => $icon,
                ])
                ->all(),
            placeholder: 'Type to search icons (e.g., "arrow", "cog", "user")...',
        );
    }

    protected function normalizeAppName(string $name): string
    {
        // Replace spaces and hyphens with underscores and convert to uppercase
        return mb_strtoupper(preg_replace('/[\s-]+/', '_', mb_trim($name)));
    }

    protected function askToAssignTenants(TenantApp $tenantApp): void
    {
        $tenants = Tenant::orderBy('name')->get();

        if ($tenants->isEmpty()) {
            $this->newLine();
            $this->comment('No tenants found to assign.');
            return;
        }

        $this->newLine();

        if (!confirm('Would you like to assign this app to tenants?', default: true)) {
            $this->comment('Run "php artisan noerd:assign-apps-to-tenant" later to assign.');
            return;
        }

        $tenantChoices = [];
        $allTenantIds = [];
        foreach ($tenants as $tenant) {
            $tenantChoices[$tenant->id] = $tenant->name;
            $allTenantIds[] = $tenant->id;
        }

        $selectedTenantIds = multiselect(
            label: 'Select tenants to assign this app to:',
            options: $tenantChoices,
            default: $allTenantIds,
            scroll: 10,
            required: false,
        );

        if (!empty($selectedTenantIds)) {
            $tenantApp->tenants()->sync($selectedTenantIds);
            $this->info('✅ App assigned to ' . count($selectedTenantIds) . ' tenant(s).');
        } else {
            $this->comment('No tenants selected.');
        }
    }
}
