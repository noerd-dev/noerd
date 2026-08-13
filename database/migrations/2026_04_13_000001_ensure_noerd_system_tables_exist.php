<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ensures all noerd system tables exist for upgrades from old versions
 * where the original create_noerd_system_tables migration was already
 * recorded but did not yet include all current table definitions.
 *
 * Handles three scenarios per table:
 * 1. Old name exists (e.g. 'profiles') → rename to noerd_ prefix
 * 2. Neither old nor new name exists → create from scratch
 * 3. Table already exists → skip
 */
return new class extends Migration {
    public function up(): void
    {
        $this->ensureNoerdProfiles();
        $this->ensureNoerdUserSettings();
        $this->ensureUsersTenants();
    }

    public function down(): void
    {
        // Not reversible - tables are required by the system
    }

    private function ensureNoerdProfiles(): void
    {
        if (Schema::hasTable('profiles') && !Schema::hasTable('noerd_profiles')) {
            Schema::rename('profiles', 'noerd_profiles');
        }

        if (!Schema::hasTable('noerd_profiles')) {
            Schema::create('noerd_profiles', function (Blueprint $table): void {
                $table->id();
                $table->string('key');
                $table->string('name');
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->timestamps();
            });
        }
    }

    private function ensureNoerdUserSettings(): void
    {
        if (Schema::hasTable('user_settings') && !Schema::hasTable('noerd_user_settings')) {
            Schema::rename('user_settings', 'noerd_user_settings');
        }

        if (!Schema::hasTable('noerd_user_settings') && Schema::hasTable('noerd_users')) {
            Schema::create('noerd_user_settings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('noerd_users')->cascadeOnDelete();
                $table->unsignedBigInteger('selected_tenant_id')->nullable();
                $table->foreign('selected_tenant_id')->references('id')->on('tenants')->nullOnDelete();
                $table->index('selected_tenant_id');
                $table->string('locale', 5)->default('en');
                $table->timestamps();
            });
        }
    }

    private function ensureUsersTenants(): void
    {
        if (!Schema::hasTable('users_tenants') && Schema::hasTable('noerd_users')) {
            Schema::create('users_tenants', function (Blueprint $table): void {
                $table->foreignId('user_id')->constrained('noerd_users')->cascadeOnDelete();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('profile_id')->nullable()->constrained('noerd_profiles');
                $table->timestamps();
            });
        }

        if (Schema::hasTable('users_tenants') && !Schema::hasColumn('users_tenants', 'profile_id')) {
            Schema::table('users_tenants', function (Blueprint $table): void {
                $table->foreignId('profile_id')->nullable()->constrained('noerd_profiles');
            });
        }
    }
};
