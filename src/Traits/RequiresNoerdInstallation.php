<?php

namespace Noerd\Noerd\Traits;

use function Laravel\Prompts\multiselect;

use Noerd\Noerd\Models\Tenant;
use Noerd\Noerd\Models\TenantApp;

trait RequiresNoerdInstallation
{
    /**
     * Check if noerd:install has been run
     */
    protected function ensureNoerdInstalled(): bool
    {
        if ($this->isNoerdInstalled()) {
            return true;
        }

        $this->line('');
        $this->error('Noerd base package has not been installed yet.');
        $this->line('');
        $this->info('Please run the following command first:');
        $this->line('  php artisan noerd:install');
        $this->line('');

        return false;
    }

    /**
     * Check if Noerd is installed by checking for config/noerd.php
     */
    protected function isNoerdInstalled(): bool
    {
        return file_exists(base_path('config/noerd.php'));
    }

    /**
     * Assign a specific app to selected tenants
     *
     * @param  string  $appName  The name/key of the TenantApp (e.g., 'BUSINESS-HOURS')
     */
    protected function assignAppToTenants(string $appName): void
    {
        $app = TenantApp::where('name', $appName)->first();

        if (! $app) {
            $this->warn("App '{$appName}' not found in database.");

            return;
        }

        $tenants = Tenant::orderBy('name')->get();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found.');

            return;
        }

        // Build tenant choices
        $tenantChoices = [];
        foreach ($tenants as $tenant) {
            $hasApp = $tenant->tenantApps()->where('tenant_apps.id', $app->id)->exists();
            $status = $hasApp ? ' [already assigned]' : '';
            $tenantChoices[$tenant->id] = "{$tenant->name}{$status}";
        }

        // Get currently assigned tenant IDs for this app
        $currentTenantIds = $app->tenants()->pluck('tenants.id')->toArray();

        $this->line('');
        $this->info("Assign '{$app->title}' to tenants:");
        $this->comment('Use ↑/↓ to navigate, Space to select, Enter to confirm');
        $this->line('');

        $selectedTenantIds = multiselect(
            label: "Which tenants should '{$app->title}' be assigned to?",
            options: $tenantChoices,
            default: $currentTenantIds,
            required: false,
        );

        // Sync the app to selected tenants
        foreach ($tenants as $tenant) {
            $isSelected = in_array($tenant->id, $selectedTenantIds);
            $wasAssigned = in_array($tenant->id, $currentTenantIds);

            if ($isSelected && ! $wasAssigned) {
                $tenant->tenantApps()->attach($app->id);
                $this->line("<info>✓ '{$app->title}' assigned to '{$tenant->name}'</info>");
            } elseif (! $isSelected && $wasAssigned) {
                $tenant->tenantApps()->detach($app->id);
                $this->line("<comment>✗ '{$app->title}' removed from '{$tenant->name}'</comment>");
            }
        }

        $finalCount = $app->fresh()->tenants()->count();
        $this->line('');
        $this->info("'{$app->title}' is now assigned to {$finalCount} tenant(s).");
    }
}
