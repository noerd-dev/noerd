<?php

namespace Nywerk\Noerd\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Nywerk\Noerd\Models\Profile;
use Nywerk\Noerd\Models\Tenant;
use Nywerk\Noerd\Models\User;

class AddUsersToDefaultTenant extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'noerd:add-users-to-default-tenant';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add all users to the default "Standard Mandant" tenant';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Find the default tenant
        $defaultTenant = Tenant::where('name', 'Standard Mandant')->first();
        
        if (!$defaultTenant) {
            $this->error('Standard Mandant tenant not found. Please run the migration first.');
            return self::FAILURE;
        }

        $this->info("Found default tenant: {$defaultTenant->name} (ID: {$defaultTenant->id})");

        // Get profiles for this tenant
        $userProfile = Profile::where('tenant_id', $defaultTenant->id)
            ->where('key', 'USER')
            ->first();
            
        $adminProfile = Profile::where('tenant_id', $defaultTenant->id)
            ->where('key', 'ADMIN')
            ->first();

        if (!$userProfile || !$adminProfile) {
            $this->error('USER or ADMIN profile not found for the default tenant.');
            return self::FAILURE;
        }

        $this->info("Found profiles - USER: {$userProfile->id}, ADMIN: {$adminProfile->id}");

        // Get all users
        $users = User::all();
        $this->info("Processing {$users->count()} users...");

        $usersAdded = 0;
        $usersSkipped = 0;
        $adminsAdded = 0;

        foreach ($users as $user) {
            // Check if user already has access to this tenant
            $existingAccess = DB::table('users_tenants')
                ->where('user_id', $user->id)
                ->where('tenant_id', $defaultTenant->id)
                ->exists();

            if ($existingAccess) {
                $this->line("  - Skipping {$user->name} ({$user->email}) - already has access");
                $usersSkipped++;
                continue;
            }

            // Determine profile: check if user has any ADMIN profile on other tenants
            $hasAdminProfile = DB::table('users_tenants')
                ->join('profiles', 'users_tenants.profile_id', '=', 'profiles.id')
                ->where('users_tenants.user_id', $user->id)
                ->where('profiles.key', 'ADMIN')
                ->exists();
            
            $profileId = $hasAdminProfile ? $adminProfile->id : $userProfile->id;
            $profileType = $hasAdminProfile ? 'ADMIN' : 'USER';

            // Add user to tenant
            DB::table('users_tenants')->insert([
                'user_id' => $user->id,
                'tenant_id' => $defaultTenant->id,
                'profile_id' => $profileId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->info("  ✓ Added {$user->name} ({$user->email}) with {$profileType} profile");
            $usersAdded++;
            
            if ($hasAdminProfile) {
                $adminsAdded++;
            }
        }

        // Update users without selected_tenant_id
        $usersWithoutTenant = User::whereNull('selected_tenant_id')->count();
        if ($usersWithoutTenant > 0) {
            User::whereNull('selected_tenant_id')->update([
                'selected_tenant_id' => $defaultTenant->id
            ]);
            $this->info("Updated {$usersWithoutTenant} users to use default tenant as selected tenant");
        }

        // Summary
        $this->newLine();
        $this->info('Summary:');
        $this->line("- Users added to tenant: {$usersAdded}");
        $this->line("- Users skipped (already had access): {$usersSkipped}");
        $this->line("- Users with ADMIN access: {$adminsAdded}");
        $this->line("- Users with USER access: " . ($usersAdded - $adminsAdded));

        $this->newLine();
        $this->info("✅ All users now have access to the 'Standard Mandant' tenant!");

        return self::SUCCESS;
    }
} 