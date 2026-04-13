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
return new class () extends Migration {
    public function up(): void
    {
        $this->ensureNoerdProfiles();
        $this->ensureNoerdUserSettings();
        $this->ensureNoerdUserRoles();
        $this->ensureNoerdUserRole();
        $this->ensureUsersTenants();
    }

    public function down(): void
    {
        // Not reversible - tables are required by the system
    }

    private function ensureNoerdProfiles(): void
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
    }

    private function ensureNoerdUserSettings(): void
    {
        if (Schema::hasTable('user_settings') && ! Schema::hasTable('noerd_user_settings')) {
            Schema::rename('user_settings', 'noerd_user_settings');
        }

        if (! Schema::hasTable('noerd_user_settings') && Schema::hasTable('noerd_users')) {
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

    private function ensureNoerdUserRoles(): void
    {
        if (Schema::hasTable('user_roles') && ! Schema::hasTable('noerd_user_roles')) {
            Schema::rename('user_roles', 'noerd_user_roles');
        }

        if (! Schema::hasTable('noerd_user_roles')) {
            Schema::create('noerd_user_roles', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('key');
                $table->string('name');
                $table->string('description')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->index('tenant_id');
            });
        }
    }

    private function ensureNoerdUserRole(): void
    {
        if (Schema::hasTable('user_role') && ! Schema::hasTable('noerd_user_role')) {
            Schema::rename('user_role', 'noerd_user_role');
        }

        if (Schema::hasTable('noerd_user_role') && Schema::hasColumn('noerd_user_role', 'user_role_id')) {
            Schema::table('noerd_user_role', function (Blueprint $table): void {
                $table->renameColumn('user_role_id', 'noerd_user_role_id');
            });
        }

        if (! Schema::hasTable('noerd_user_role')) {
            Schema::create('noerd_user_role', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('noerd_user_role_id');
                $table->timestamps();

                $table->index('user_id');
                $table->index('noerd_user_role_id');
                $table->unique(['user_id', 'noerd_user_role_id']);
            });
        }
    }

    private function ensureUsersTenants(): void
    {
        if (! Schema::hasTable('users_tenants') && Schema::hasTable('noerd_users')) {
            Schema::create('users_tenants', function (Blueprint $table): void {
                $table->foreignId('user_id')->constrained('noerd_users')->cascadeOnDelete();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('profile_id')->nullable()->constrained('noerd_profiles');
                $table->timestamps();
            });
        }

        if (Schema::hasTable('users_tenants') && ! Schema::hasColumn('users_tenants', 'profile_id')) {
            Schema::table('users_tenants', function (Blueprint $table): void {
                $table->foreignId('profile_id')->nullable()->constrained('noerd_profiles');
            });
        }
    }
};
