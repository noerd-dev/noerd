<?php

declare(strict_types=1);

namespace Noerd\Traits;

use Illuminate\Console\Command;

use function Laravel\Prompts\multiselect;

use Noerd\Events\TenantAppAssigned;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;
use Noerd\Support\ModuleInstallContext;

trait RequiresNoerdInstallation
{
    /**
     * Ensure the noerd base package is installed.
     *
     * An INSTALL command is a legitimate entry point into a fresh project, so
     * `noerd:install-{module}` runs `noerd:install` for the user instead of
     * dead-ending with an instruction to run it by hand. Every other command
     * (update, scaffold, demo) keeps asking for it — see shouldAutoInstallNoerd().
     *
     * @param  bool|null  $autoInstall  null = decide from the command name
     */
    protected function ensureNoerdInstalled(?bool $autoInstall = null): bool
    {
        if ($this->isNoerdInstalled()) {
            return true;
        }

        if ($autoInstall ?? $this->shouldAutoInstallNoerd()) {
            return $this->installNoerdBase();
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
     * Only an install command installs the base package on the fly: an update,
     * a scaffold run or the demo installer is never the first command a fresh
     * project is expected to run.
     */
    protected function shouldAutoInstallNoerd(): bool
    {
        return str_starts_with((string) $this->getName(), 'noerd:install');
    }

    /**
     * Run noerd:install and report whether the base package is installed afterwards.
     */
    protected function installNoerdBase(): bool
    {
        $this->line('');
        $this->info('Noerd base package has not been installed yet — running "php artisan noerd:install" first.');
        $this->line('');

        // As a dependency: the base installer skips its closing "Application ready"
        // callout, so the run ends with the module's own one instead of showing a
        // finished-looking box halfway through.
        $exitCode = (int) ModuleInstallContext::asDependency(
            fn(): int => $this->call('noerd:install', $this->noerdInstallOptions()),
        );

        if ($exitCode !== Command::SUCCESS || ! $this->isNoerdInstalled()) {
            $this->line('');
            $this->error('The noerd base installation did not complete.');
            $this->line('');
            $this->info('Please run the following command and try again:');
            $this->line('  php artisan noerd:install');
            $this->line('');

            return false;
        }

        $this->line('');
        $this->info('Noerd base package installed. Continuing...');
        $this->line('');

        return true;
    }

    /**
     * Options the caller shares with noerd:install, so an automatic base
     * installation follows the same choices the module command was given.
     * --no-interaction, --quiet and the verbosity flags are forwarded by
     * Command::call() itself.
     *
     * The demo app is skipped unless the caller asked for it: someone installing
     * a module is setting up that module, not looking for the demo — so the
     * implicit base installation must not stop to ask about it.
     *
     * @return array<string, bool>
     */
    protected function noerdInstallOptions(): array
    {
        $options = [];

        foreach (['force', 'migrate', 'build', 'demo'] as $option) {
            if ($this->getDefinition()->hasOption($option) && (bool) $this->option($option)) {
                $options['--' . $option] = true;
            }
        }

        if (! isset($options['--demo'])) {
            $options['--no-demo'] = true;
        }

        return $options;
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
     * @param  bool  $compact  Only the selection and the final count — no header, no per-tenant lines
     * @param  array<string>  $alsoAssign  Apps the module cannot work without (HasModuleInstallation::getRequiredAppKeys()).
     *                                     They follow the same selection, but ADDITIVELY: a tenant that
     *                                     loses this app keeps them, because another module may need them.
     */
    protected function assignAppToTenants(string $appName, bool $compact = false, array $alsoAssign = []): void
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

        // Default to all tenants selected
        $allTenantIds = $tenants->pluck('id')->toArray();

        if (! $compact) {
            $this->line('');
            $this->info("Assign '{$app->title}' to tenants:");
            $this->comment('Use ↑/↓ to navigate, Space to select, Enter to confirm');
            $this->line('');
        }

        $selectedTenantIds = multiselect(
            label: "Which tenants should '{$app->title}' be assigned to?",
            options: $tenantChoices,
            default: $allTenantIds,
            required: false,
        );

        // Sync the app to selected tenants
        foreach ($tenants as $tenant) {
            $isSelected = in_array((int) $tenant->id, array_map('intval', $selectedTenantIds), true);
            $wasAssigned = in_array((int) $tenant->id, array_map('intval', $currentTenantIds), true);

            if ($isSelected && ! $wasAssigned) {
                $tenant->tenantApps()->attach($app->id);
                TenantAppAssigned::dispatch($tenant->id, $appName);
                if (! $compact) {
                    $this->line("<info>✓ '{$app->title}' assigned to '{$tenant->name}'</info>");
                }
            } elseif (! $isSelected && $wasAssigned) {
                $tenant->tenantApps()->detach($app->id);
                if (! $compact) {
                    $this->line("<comment>✗ '{$app->title}' removed from '{$tenant->name}'</comment>");
                }
            }
        }

        $finalCount = $app->fresh()->tenants()->count();
        if (! $compact) {
            $this->line('');
        }
        $this->info("'{$app->title}' is now assigned to {$finalCount} tenant(s).");

        $this->assignRequiredApps($alsoAssign, $selectedTenantIds, $compact);
    }

    /**
     * Give every tenant that just got the app the apps it cannot work without.
     * Purely additive — a required app is never detached, it may be the reason
     * another installed module works.
     *
     * @param  array<string>  $appNames
     * @param  array<int|string>  $tenantIds
     */
    protected function assignRequiredApps(array $appNames, array $tenantIds, bool $compact = false): void
    {
        if ($appNames === [] || $tenantIds === []) {
            return;
        }

        $tenants = Tenant::whereIn('id', array_map('intval', $tenantIds))->orderBy('name')->get();

        foreach ($appNames as $appName) {
            $required = TenantApp::where('name', $appName)->first();

            if (! $required) {
                continue;
            }

            $assignedIds = $required->tenants()->pluck('tenants.id')->map('intval')->all();
            $attached = 0;

            foreach ($tenants as $tenant) {
                if (in_array((int) $tenant->id, $assignedIds, true)) {
                    continue;
                }

                $tenant->tenantApps()->attach($required->id);
                TenantAppAssigned::dispatch($tenant->id, $appName);
                $attached++;

                if (! $compact) {
                    $this->line("<info>✓ '{$required->title}' assigned to '{$tenant->name}' (required)</info>");
                }
            }

            if ($attached > 0) {
                $this->info("'{$required->title}' is required and was assigned to {$attached} tenant(s).");
            }
        }
    }
}
