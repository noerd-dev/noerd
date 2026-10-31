<?php

declare(strict_types=1);

namespace Noerd\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Noerd\Commands\Concerns\ResolvesNoerdUser;
use Noerd\Enums\Profile;
use Noerd\Models\Tenant;

/**
 * Gives an EXISTING user the admin profile on every tenant they belong to. A
 * user without any membership is attached to every tenant first — that is how
 * the first account of an installation (noerd:install, noerd:make-admin-user)
 * becomes its administrator.
 */
class PromoteAdminCommand extends Command
{
    use ResolvesNoerdUser;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'noerd:promote-admin {user : The ID or email of the user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make a user admin by giving them admin profile access on all their tenants';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $identifier = (string) $this->argument('user');
        $user = $this->resolveNoerdUser($identifier);

        if (! $user) {
            $this->error("User '{$identifier}' not found.");

            return self::FAILURE;
        }

        $this->info("Processing user: {$user->name} ({$user->email})");

        $isAlreadyAdmin = $user->isAdminOfAnyTenant();
        if ($isAlreadyAdmin) {
            $this->warn('User is already an admin. Ensuring tenant assignment is correct...');
        }

        if ($user->tenants()->doesntExist()) {
            foreach (Tenant::query()->orderBy('id')->pluck('id') as $tenantId) {
                $user->tenants()->attach($tenantId, ['profile_key' => null]);
            }
        }

        $memberTenantIds = $this->tenantIds($user->tenants());
        $adminTenantIds = $this->tenantIds($user->tenants()->wherePivot('profile_key', Profile::Admin->value));

        $tenants = Tenant::query()->whereIn('id', $memberTenantIds)->orderBy('id')->get();
        $this->info("User has access to {$tenants->count()} tenant(s).");

        $granted = 0;

        foreach ($tenants as $tenant) {
            $this->line("Processing tenant: {$tenant->name}");

            if (in_array($tenant->id, $adminTenantIds, true)) {
                $this->line("  - User already has ADMIN access for tenant: {$tenant->name}");

                continue;
            }

            $user->tenants()->updateExistingPivot($tenant->id, ['profile_key' => Profile::Admin->value]);
            $granted++;
            $this->info("  Granted ADMIN access for tenant: {$tenant->name}");
        }

        // The selected tenant must be one the user belongs to: the current
        // selection stays when it is, otherwise the first of their tenants is taken.
        $selected = $tenants->first(fn(Tenant $tenant): bool => $tenant->id === $user->selected_tenant_id)
            ?? $tenants->first();

        if ($selected) {
            $user->selected_tenant_id = $selected->id;
            $user->save();
            $this->info("Set user's selected_tenant_id to tenant ID: {$selected->id}");
        } else {
            $this->warn('No tenants found to assign as selected_tenant_id.');
        }

        $this->newLine();
        $this->info('Summary:');
        $this->line("- ADMIN access granted: {$granted}");

        $user->refresh();
        if (! $user->isAdminOfAnyTenant()) {
            $this->error('Failed to make user admin. Please check the database.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info($isAlreadyAdmin
            ? "User {$user->name} remains an admin. Tenant assignment verified."
            : "User {$user->name} is now an admin with access to Setup!");

        return self::SUCCESS;
    }

    /**
     * The ids of the tenants a membership query yields.
     *
     * @return list<int>
     */
    private function tenantIds(BelongsToMany $memberships): array
    {
        return $memberships->pluck('tenants.id')->map(fn($id): int => (int) $id)->values()->all();
    }
}
