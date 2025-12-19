<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Noerd\Noerd\Models\Profile;
use Noerd\Noerd\Models\Tenant;
use Noerd\Noerd\Models\User;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create default tenant if it doesn't exist
        $this->createDefaultTenant();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Find the default tenant
        $defaultTenant = Tenant::where('name', 'Standard Mandant')->first();

        if ($defaultTenant) {
            // Remove all user associations with this tenant
            DB::table('users_tenants')
                ->where('tenant_id', $defaultTenant->id)
                ->delete();

            // Remove tenant apps associations
            DB::table('tenant_app')
                ->where('tenant_id', $defaultTenant->id)
                ->delete();

            // Delete profiles for this tenant
            Profile::where('tenant_id', $defaultTenant->id)->delete();

            // Update users who had this as selected tenant
            User::where('selected_tenant_id', $defaultTenant->id)
                ->update(['selected_tenant_id' => null]);

            // Delete the tenant
            $defaultTenant->delete();
        }
    }

    /**
     * Create default tenant and setup user associations
     */
    private function createDefaultTenant(): void
    {
        // Create a default tenant if it doesn't exist
        $defaultTenant = Tenant::where('name', 'Standard Mandant')->first();

        if (!$defaultTenant) {
            // Use DB insert to avoid triggering observers/events during migration
            $tenantId = DB::table('tenants')->insertGetId([
                'name' => 'Standard Mandant',
                'hash' => Str::uuid()->toString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create default profiles for the tenant using direct DB inserts
            $userProfileId = DB::table('profiles')->insertGetId([
                'tenant_id' => $tenantId,
                'key' => 'USER',
                'name' => 'User',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $adminProfileId = DB::table('profiles')->insertGetId([
                'tenant_id' => $tenantId,
                'key' => 'ADMIN',
                'name' => 'Admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Grant all existing users access to this tenant with USER profile
            $users = User::with('profiles')->get();
            foreach ($users as $user) {
                // Check if user already has access to this tenant
                $existingAccess = DB::table('users_tenants')
                    ->where('user_id', $user->id)
                    ->where('tenant_id', $tenantId)
                    ->exists();

                if (!$existingAccess) {
                    // Determine profile: check if user has any ADMIN profile
                    $hasAdminProfile = DB::table('users_tenants')
                        ->join('profiles', 'users_tenants.profile_id', '=', 'profiles.id')
                        ->where('users_tenants.user_id', $user->id)
                        ->where('profiles.key', 'ADMIN')
                        ->exists();

                    $profileId = $hasAdminProfile ? $adminProfileId : $userProfileId;

                    DB::table('users_tenants')->insert([
                        'user_id' => $user->id,
                        'tenant_id' => $tenantId,
                        'profile_id' => $profileId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
};
