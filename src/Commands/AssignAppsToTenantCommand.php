<?php

declare(strict_types=1);

namespace Noerd\Commands;

use Exception;
use Illuminate\Console\Command;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\search;
use function Laravel\Prompts\select;

use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;

class AssignAppsToTenantCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'noerd:assign-apps-to-tenant
                            {--tenant-id= : The ID of the tenant to assign apps to (required in non-interactive runs)}
                            {--apps=* : Assign these apps without asking — app names, case-insensitive (--apps=crm --apps=MEDIA); nothing is removed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign apps to a tenant — interactively, or scripted with --tenant-id and --apps';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantOption = $this->option('tenant-id');
        $tenantId = null;

        // Options arrive as strings: validate before anything is looked up.
        if ($tenantOption !== null && $tenantOption !== '') {
            if (! is_int($tenantOption) && ! (is_string($tenantOption) && ctype_digit($tenantOption))) {
                $this->error('--tenant-id expects the numeric id of a tenant, got "' . (is_scalar($tenantOption) ? (string) $tenantOption : get_debug_type($tenantOption)) . '".');

                return self::FAILURE;
            }

            $tenantId = (int) $tenantOption;
        }

        $appNames = array_values(array_filter(
            array_map(static fn(mixed $name): string => mb_trim((string) $name), (array) $this->option('apps')),
            static fn(string $name): bool => $name !== '',
        ));

        // A select prompt without a person picks its first option — never guess the tenant.
        if ($tenantId === null && ! $this->input->isInteractive()) {
            $this->error('Non-interactive run: pass --tenant-id (and --apps to assign without the selection).');

            return self::FAILURE;
        }

        try {
            // Step 1: Select Tenant
            $selectedTenant = $this->selectTenant($tenantId);
            if (! $selectedTenant) {
                return self::FAILURE;
            }

            if ($appNames !== []) {
                return $this->assignNamedApps($selectedTenant, $appNames) ? self::SUCCESS : self::FAILURE;
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
            if (! $tenant) {
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
     * Assign the apps named by --apps (case-insensitive) — additively, nothing
     * the tenant holds is removed. An unknown or inactive name assigns nothing.
     *
     * @param  list<string>  $names
     */
    private function assignNamedApps(Tenant $tenant, array $names): bool
    {
        $wanted = array_values(array_unique(array_map(mb_strtoupper(...), $names)));
        $apps = TenantApp::where('is_active', true)
            ->get()
            ->filter(fn(TenantApp $app): bool => in_array(mb_strtoupper((string) $app->name), $wanted, true));

        $unknown = array_diff($wanted, $apps->map(fn(TenantApp $app): string => mb_strtoupper((string) $app->name))->all());

        if ($unknown !== []) {
            $this->error('Unknown or inactive app(s): ' . implode(', ', $unknown) . ' — nothing was assigned.');

            return false;
        }

        $currentAppIds = $tenant->tenantApps()->pluck('tenant_apps.id')->map(fn($id): int => (int) $id)->all();
        $added = [];

        foreach ($apps as $app) {
            if (in_array((int) $app->id, $currentAppIds, true)) {
                continue;
            }

            $tenant->assignApp($app);
            $added[] = $app->title;
        }

        $this->info($added === []
            ? "Tenant '{$tenant->name}' already holds every named app."
            : 'Added apps: ' . implode(', ', $added));

        return true;
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
        if (! empty($currentAppIds)) {
            $this->info('Currently assigned apps:');
            foreach ($allApps->whereIn('id', $currentAppIds) as $app) {
                $this->line("  {$app->title} ({$app->name})");
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
            $addedApps = array_diff($selectedAppIds, $currentAppIds);
            $removedApps = array_diff($currentAppIds, $selectedAppIds);

            // Removals and additions go through the one write path of each
            // (TenantAppRemoved / TenantAppAssigned fire per changed app).
            foreach (TenantApp::whereIn('id', $removedApps)->get() as $removedApp) {
                $tenant->removeApp($removedApp);
            }
            foreach (TenantApp::whereIn('id', $addedApps)->get() as $addedApp) {
                $tenant->assignApp($addedApp);
            }

            $this->newLine();
            $this->info('App assignments updated successfully!');

            if (! empty($addedApps)) {
                $addedAppNames = TenantApp::whereIn('id', $addedApps)->pluck('title')->toArray();
                $this->info('Added apps: ' . implode(', ', $addedAppNames));
            }

            if (! empty($removedApps)) {
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
