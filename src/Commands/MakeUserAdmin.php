<?php

namespace Noerd\Noerd\Commands;

use Illuminate\Console\Command;
use Noerd\Noerd\Models\Profile;
use Noerd\Noerd\Models\Tenant;
use Noerd\Noerd\Models\User;

class MakeUserAdmin extends Command
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
        $user = User::find($userId);
        if (!$user) {
            $this->error("User with ID {$userId} not found.");
            return self::FAILURE;
        }

        $this->info("Processing user: {$user->name} ({$user->email})");

        // Check if user already is admin
        if ($user->isAdmin()) {
            $this->warn('User is already an admin.');
            return self::SUCCESS;
        }

        // Get all tenants the user has access to
        $userTenants = $user->tenants;

        if ($userTenants->isEmpty()) {
            // Assign to all tenants if no specific tenant access
            $userTenants = Tenant::all();
            foreach ($userTenants as $userTenant) {
                if (!$user->tenants->contains($userTenant)) {
                    $user->tenants()->attach($userTenant->id, ['profile_id' => null]);
                }
            }
        }

        $this->info("User has access to {$userTenants->count()} tenant(s).");

        $adminProfilesCreated = 0;
        $adminAccessGranted = 0;

        foreach ($userTenants as $tenant) {
            $this->line("Processing tenant: {$tenant->name}");

            // Check if admin profile exists for this tenant
            $adminProfile = Profile::where('tenant_id', $tenant->id)
                ->where('key', 'ADMIN')
                ->first();

            if (!$adminProfile) {
                // Create admin profile for this tenant
                $adminProfile = Profile::create([
                    'tenant_id' => $tenant->id,
                    'key' => 'ADMIN',
                    'name' => 'Administrator',
                ]);
                $adminProfilesCreated++;
                $this->info("  ✓ Created ADMIN profile for tenant: {$tenant->name}");
            } else {
                $this->line("  - ADMIN profile already exists for tenant: {$tenant->name}");
            }

            // Check if user already has admin profile for this tenant
            $currentProfile = $user->tenants()
                ->where('tenant_id', $tenant->id)
                ->first();

            if ($currentProfile && $currentProfile->pivot->profile_id === $adminProfile->id) {
                $this->line("  - User already has ADMIN access for tenant: {$tenant->name}");
                continue;
            }

            // Update user's profile to admin for this tenant
            $user->tenants()->updateExistingPivot($tenant->id, [
                'profile_id' => $adminProfile->id,
            ]);
            $adminAccessGranted++;
            $this->info("  ✓ Granted ADMIN access for tenant: {$tenant->name}");
        }

        // Summary
        $this->newLine();
        $this->info('Summary:');
        $this->line("- ADMIN profiles created: {$adminProfilesCreated}");
        $this->line("- ADMIN access granted: {$adminAccessGranted}");

        // Verify admin status
        $user->refresh();
        if ($user->isAdmin()) {
            $this->newLine();
            $this->info("✅ User {$user->name} is now an admin with access to Setup!");
        } else {
            $this->error("❌ Failed to make user admin. Please check the database.");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
