<?php

namespace Noerd\Noerd\Commands;

use Exception;
use Illuminate\Console\Command;
use Noerd\Noerd\Models\TenantApp;

class CreateTenantApp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'noerd:create-tenant-app 
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
    protected $description = 'Create a new tenant app that can be assigned to tenants';

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
            $name = $name ?: $this->ask('App Name (unique identifier, e.g., CMS, MEDIA)');
            $icon = $icon ?: $this->ask('App Icon (e.g., icons.planning, icons.media)');
            $route = $route ?: $this->ask('App Route (main route name, e.g., cms.pages, media.dashboard)');
        }

        // Validate required fields
        if (!$title || !$name || !$icon || !$route) {
            $this->error('All fields (title, name, icon, route) are required.');
            return self::FAILURE;
        }

        // Validate name format (uppercase, no spaces)
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

            $this->newLine();
            $this->comment('The app can now be assigned to tenants through the tenant management system.');

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error("Failed to create tenant app: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
