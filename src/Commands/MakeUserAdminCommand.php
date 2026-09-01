<?php

namespace Noerd\Commands;

use Illuminate\Console\Command;
use Noerd\Enums\Profile;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;

class MakeUserAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'noerd:make-admin {user_id : The ID of the user to make admin}';

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
        $userId = $this->argument('user_id');

        // Validate user ID
        if (!is_numeric($userId)) {
            $this->error('User ID must be a number.');
            return self::FAILURE;
        }

        // Find the user
        $user = NoerdUser::find($userId);
        if (!$user) {
            $this->error("User with ID {$userId} not found.");
            return self::FAILURE;
        }

        $this->info("Processing user: {$user->name} ({$user->email})");

        // Check if user already is admin (but continue to ensure tenant assignment)
        $isAlreadyAdmin = $user->isAdminOfAnyTenant();
        if ($isAlreadyAdmin) {
            $this->warn('User is already an admin. Ensuring tenant assignment is correct...');
        }

        // Get all tenants the user has access to
        $userTenants = $user->tenants;

        if ($userTenants->isEmpty()) {
            // Assign to all tenants if no specific tenant access
            $userTenants = Tenant::all();
            foreach ($userTenants as $userTenant) {
                if (!$user->tenants->contains($userTenant)) {
                    $user->tenants()->attach($userTenant->id, ['profile_key' => null]);
                }
            }
        }

        $this->info("User has access to {$userTenants->count()} tenant(s).");

        $adminAccessGranted = 0;

        foreach ($userTenants as $tenant) {
            $this->line("Processing tenant: {$tenant->name}");

            // Check if user already has the admin profile for this tenant
            $membership = $user->tenants()
                ->where('tenant_id', $tenant->id)
                ->first();

            if ($membership && $membership->pivot->profile_key === Profile::Admin->value) {
                $this->line("  - User already has ADMIN access for tenant: {$tenant->name}");
                continue;
            }

            // Update user's profile to admin for this tenant
            $user->tenants()->updateExistingPivot($tenant->id, [
                'profile_key' => Profile::Admin->value,
            ]);
            $adminAccessGranted++;
            $this->info("  ✓ Granted ADMIN access for tenant: {$tenant->name}");
        }

        // Ensure selected_tenant_id is set to the first available tenant
        $firstTenantId = Tenant::query()->orderBy('id')->value('id');
        if ($firstTenantId) {
            $user->selected_tenant_id = $firstTenantId;
            $user->save();
            $this->info("Set user's selected_tenant_id to tenant ID: {$firstTenantId}");
        } else {
            $this->warn('No tenants found to assign as selected_tenant_id.');
        }

        // Summary
        $this->newLine();
        $this->info('Summary:');
        $this->line("- ADMIN access granted: {$adminAccessGranted}");

        // Verify admin status
        $user->refresh();
        if ($user->isAdminOfAnyTenant()) {
            $this->newLine();
            if ($isAlreadyAdmin) {
                $this->info("✅ User {$user->name} remains an admin. Tenant assignment verified.");
            } else {
                $this->info("✅ User {$user->name} is now an admin with access to Setup!");
            }
        } else {
            $this->error("❌ Failed to make user admin. Please check the database.");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
