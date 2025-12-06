<?php

namespace Noerd\Noerd\Traits;

use Exception;
use Illuminate\Support\Facades\Artisan;

use function Laravel\Prompts\multiselect;

use Noerd\Noerd\Models\Tenant;
use Noerd\Noerd\Models\TenantApp;

trait RequiresNoerdInstallation
{
    /**
     * Check if noerd:install has been run, and run it if not
     */
    protected function ensureNoerdInstalled(): bool
    {
        if ($this->isNoerdInstalled()) {
            $this->line('<comment>Noerd base package already installed.</comment>');

            return true;
        }

        $this->line('');
        $this->warn('Noerd base package has not been installed yet.');
        $this->info('Running noerd:install first...');
        $this->line('');

        try {
            $options = $this->option('force') ? ['--force' => true] : [];
            $exitCode = Artisan::call('noerd:install', $options, $this->output);

            if ($exitCode === 0) {
                $this->line('');
                $this->info('Noerd base package installed successfully.');
                $this->line('');

                return true;
            }

            $this->error('Failed to install noerd base package.');

            return false;
        } catch (Exception $e) {
            $this->error('Failed to run noerd:install: ' . $e->getMessage());

            return false;
        }
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
            $this->warn("App '{$appName}' nicht in der Datenbank gefunden.");

            return;
        }

        $tenants = Tenant::orderBy('name')->get();

        if ($tenants->isEmpty()) {
            $this->warn('Keine Tenants gefunden.');

            return;
        }

        // Build tenant choices
        $tenantChoices = [];
        foreach ($tenants as $tenant) {
            $hasApp = $tenant->tenantApps()->where('tenant_apps.id', $app->id)->exists();
            $status = $hasApp ? ' [bereits zugewiesen]' : '';
            $tenantChoices[$tenant->id] = "{$tenant->name}{$status}";
        }

        // Get currently assigned tenant IDs for this app
        $currentTenantIds = $app->tenants()->pluck('tenants.id')->toArray();

        $this->line('');
        $this->info("App '{$app->title}' Tenants zuweisen:");
        $this->comment('Nutze ↑/↓ zum Navigieren, Leertaste zum Auswählen, Enter zum Bestätigen');
        $this->line('');

        $selectedTenantIds = multiselect(
            label: "Welchen Tenants soll '{$app->title}' zugewiesen werden?",
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
                $this->line("<info>✓ '{$app->title}' wurde '{$tenant->name}' zugewiesen</info>");
            } elseif (! $isSelected && $wasAssigned) {
                $tenant->tenantApps()->detach($app->id);
                $this->line("<comment>✗ '{$app->title}' wurde von '{$tenant->name}' entfernt</comment>");
            }
        }

        $finalCount = $app->fresh()->tenants()->count();
        $this->line('');
        $this->info("'{$app->title}' ist jetzt {$finalCount} Tenant(s) zugewiesen.");
    }
}
