<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Noerd\Noerd\Models\Profile;
use Noerd\Noerd\Models\Tenant;
use Noerd\Noerd\Models\User;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create a default tenant if it doesn't exist
        $defaultTenant = Tenant::where('name', 'Standard Mandant')->first();

        if (!$defaultTenant) {
            $defaultTenant = Tenant::create([
                'name' => 'Standard Mandant',
                'hash' => Str::uuid(),
                'module' => 'STANDARD',
                'period' => 'MONTHLY',
                'demo_user' => false,
                'package' => 1,
                'tax_percentage' => 19.00,
                'free' => true,
                'invoice_number' => 0,
                'icon' => 'https://liefertool.de/svg/liefertool.svg',
            ]);

            // Create default profiles for the tenant
            $userProfile = Profile::create([
                'tenant_id' => $defaultTenant->id,
                'key' => 'USER',
                'name' => 'Benutzer',
            ]);

            $adminProfile = Profile::create([
                'tenant_id' => $defaultTenant->id,
                'key' => 'ADMIN',
                'name' => 'Administrator',
            ]);

            // Grant all existing users access to this tenant with USER profile
            $users = User::with('profiles')->get();
            foreach ($users as $user) {
                // Check if user already has access to this tenant
                $existingAccess = DB::table('users_tenants')
                    ->where('user_id', $user->id)
                    ->where('tenant_id', $defaultTenant->id)
                    ->exists();

                if (!$existingAccess) {
                    // Determine profile: check if user has any ADMIN profile
                    $hasAdminProfile = DB::table('users_tenants')
                        ->join('profiles', 'users_tenants.profile_id', '=', 'profiles.id')
                        ->where('users_tenants.user_id', $user->id)
                        ->where('profiles.key', 'ADMIN')
                        ->exists();

                    $profileId = $hasAdminProfile ? $adminProfile->id : $userProfile->id;

                    DB::table('users_tenants')->insert([
                        'user_id' => $user->id,
                        'tenant_id' => $defaultTenant->id,
                        'profile_id' => $profileId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Set this tenant as selected_tenant_id for users who don't have one
            User::whereNull('selected_tenant_id')->update([
                'selected_tenant_id' => $defaultTenant->id
            ]);
        }
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
};
