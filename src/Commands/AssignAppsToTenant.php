<?php

namespace Noerd\Commands;

use Exception;
use Illuminate\Console\Command;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\search;
use function Laravel\Prompts\select;

use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;

class AssignAppsToTenant extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'noerd:assign-apps-to-tenant
                            {--tenant-id= : The ID of the tenant to assign apps to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign apps to a tenant with interactive selection';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantId = $this->option('tenant-id');

        try {
            // Step 1: Select Tenant
            $selectedTenant = $this->selectTenant($tenantId);
            if (!$selectedTenant) {
                return self::FAILURE;
            }

            // Step 2: Show current apps and allow multi-selection
            $result = $this->manageAppsForTenant($selectedTenant);

            return $result ? self::SUCCESS : self::FAILURE;
        } catch (Exception $e) {
            $this->error("Command failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    /**
     * Select a tenant using Laravel Prompts
     */
    private function selectTenant(?int $tenantId = null): ?Tenant
    {
        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                $this->error("Tenant with ID {$tenantId} not found.");
                return null;
            }
            return $tenant;
        }

        $tenants = Tenant::orderBy('name')->get();

        if ($tenants->isEmpty()) {
            $this->error('No tenants found.');
            return null;
        }

        // Build tenant choices with additional info
        $tenantChoices = [];
        foreach ($tenants as $tenant) {
            $appCount = $tenant->tenantApps()->count();
            $tenantChoices[$tenant->id] = "{$tenant->name} (ID: {$tenant->id}, Apps: {$appCount})";
        }

        // Use search for > 10 tenants, otherwise use select
        if ($tenants->count() > 10) {
            $selectedTenantId = search(
                label: 'Search for a tenant:',
                options: fn(string $query) => collect($tenantChoices)
                    ->filter(fn($label) => empty($query) || str_contains(mb_strtolower($label), mb_strtolower($query)))
                    ->all(),
                placeholder: 'Type to search tenants...',
            );
        } else {
            $selectedTenantId = select(
                label: 'Select a tenant:',
                options: $tenantChoices,
            );
        }

        return Tenant::find($selectedTenantId);
    }

    /**
     * Manage apps for the selected tenant using Laravel Prompts multiselect
     */
    private function manageAppsForTenant(Tenant $tenant): bool
    {
        $allApps = TenantApp::where('is_active', true)->orderBy('title')->get();

        if ($allApps->isEmpty()) {
            $this->error('No active apps found.');
            return false;
        }

        // Get currently assigned apps
        $currentAppIds = $tenant->tenantApps()->pluck('tenant_apps.id')->toArray();

        // Build app choices
        $appChoices = [];
        foreach ($allApps as $app) {
            $appChoices[$app->id] = "{$app->title} ({$app->name})";
        }

        $this->info("App Assignment for: {$tenant->name}");
        $this->comment('Use ↑/↓ to navigate, Space to select/deselect, Enter to confirm');
        $this->newLine();

        // Show current assignments
        if (!empty($currentAppIds)) {
            $this->info('Currently assigned apps:');
            foreach ($allApps->whereIn('id', $currentAppIds) as $app) {
                $this->line("  ✓ {$app->title} ({$app->name})");
            }
            $this->newLine();
        } else {
            $this->comment('No apps currently assigned to this tenant.');
            $this->newLine();
        }

        $selectedAppIds = multiselect(
            label: 'Select apps to assign to this tenant:',
            options: $appChoices,
            default: $currentAppIds,
            scroll: 10,
            required: false,
        );

        // Save changes
        return $this->saveAppAssignments($tenant, $selectedAppIds, $currentAppIds);
    }

    /**
     * Save app assignments to database
     */
    private function saveAppAssignments(Tenant $tenant, array $selectedAppIds, array $currentAppIds): bool
    {
        try {
            // Sync the assignments (this will add new ones and remove old ones)
            $tenant->tenantApps()->sync($selectedAppIds);

            $addedApps = array_diff($selectedAppIds, $currentAppIds);
            $removedApps = array_diff($currentAppIds, $selectedAppIds);

            $this->newLine();
            $this->info('✅ App assignments updated successfully!');

            if (!empty($addedApps)) {
                $addedAppNames = TenantApp::whereIn('id', $addedApps)->pluck('title')->toArray();
                $this->info('✓ Added apps: ' . implode(', ', $addedAppNames));
            }

            if (!empty($removedApps)) {
                $removedAppNames = TenantApp::whereIn('id', $removedApps)->pluck('title')->toArray();
                $this->info('✗ Removed apps: ' . implode(', ', $removedAppNames));
            }

            if (empty($addedApps) && empty($removedApps)) {
                $this->comment('No changes were made.');
            }

            // Show final summary
            $finalAppCount = $tenant->fresh()->tenantApps()->count();
            $this->newLine();
            $this->comment("Tenant '{$tenant->name}' now has {$finalAppCount} app(s) assigned.");

            return true;
        } catch (Exception $e) {
            $this->error("Failed to save app assignments: {$e->getMessage()}");
            return false;
        }
    }
}
