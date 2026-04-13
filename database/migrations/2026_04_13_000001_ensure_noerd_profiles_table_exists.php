<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ensures the noerd_profiles table exists for upgrades from old versions
 * where the original create_noerd_system_tables migration was already
 * recorded but did not yet include the profiles table creation.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('profiles') && ! Schema::hasTable('noerd_profiles')) {
            Schema::rename('profiles', 'noerd_profiles');
        }

        if (! Schema::hasTable('noerd_profiles')) {
            Schema::create('noerd_profiles', function (Blueprint $table): void {
                $table->id();
                $table->string('key');
                $table->string('name');
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('users_tenants') && ! Schema::hasColumn('users_tenants', 'profile_id')) {
            Schema::table('users_tenants', function (Blueprint $table): void {
                $table->foreignId('profile_id')->nullable()->constrained('noerd_profiles');
            });
        }
    }

    public function down(): void
    {
        // Not reversible - table is required by the system
    }
};
