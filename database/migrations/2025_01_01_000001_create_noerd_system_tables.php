<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create tenant_apps table
        if (!Schema::hasTable('tenant_apps')) {
            Schema::create('tenant_apps', function (Blueprint $table): void {
                $table->id();
                $table->string('title');
                $table->string('name');
                $table->string('icon');
                $table->string('route');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Create tenants table
        if (!Schema::hasTable('tenants')) {
            Schema::create('tenants', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('hash')->unique();
                $table->timestamps();
            });
        }

        // Create profiles table (before users_tenants because of foreign key)
        if (!Schema::hasTable('profiles')) {
            Schema::create('profiles', function (Blueprint $table): void {
                $table->id();
                $table->string('key');
                $table->string('name');
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->timestamps();
            });
        }

        // Create tenant_app table (pivot table)
        if (!Schema::hasTable('tenant_app')) {
            Schema::create('tenant_app', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_app_id')->constrained('tenant_apps')->onDelete('cascade');
                $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
                $table->boolean('is_hidden')->default(false);
                $table->timestamps();
            });
        }

        // Create users_tenants table (pivot table)
        if (!Schema::hasTable('users_tenants')) {
            Schema::create('users_tenants', function (Blueprint $table): void {
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
                $table->foreignId('profile_id')->nullable()->constrained('profiles');
                $table->timestamps();
            });
        }

        // Create user_roles table
        if (!Schema::hasTable('user_roles')) {
            Schema::create('user_roles', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('key');
                $table->string('name');
                $table->string('description')->nullable();
                $table->timestamps();

                // Foreign key constraint
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

                // Index
                $table->index('tenant_id');
            });
        }

        // Create user_role pivot table
        if (!Schema::hasTable('user_role')) {
            Schema::create('user_role', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('user_role_id');
                $table->timestamps();

                // Note: Foreign key constraints are not added here since the referenced tables
                // might be in different modules. They should be handled at the application level.

                // Indexes for performance
                $table->index('user_id');
                $table->index('user_role_id');

                // Unique constraint to prevent duplicate assignments
                $table->unique(['user_id', 'user_role_id']);
            });
        }

        // Create tenant_invoices table
        if (!Schema::hasTable('tenant_invoices')) {
            Schema::create('tenant_invoices', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('number')->unique();
                $table->longText('lines')->nullable();
                $table->string('customer_name')->nullable();
                $table->string('hash')->unique();
                $table->date('date')->nullable();
                $table->date('due_date')->nullable();
                $table->boolean('paid')->default(false);
                $table->decimal('total_gross_amount', 12, 2)->default(0);
                $table->boolean('sent')->default(false);
                $table->timestamp('datev_at')->nullable();
                $table->timestamps();

                $table->index('tenant_id');
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }

        // Create setup_languages table
        if (!Schema::hasTable('setup_languages')) {
            Schema::create('setup_languages', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 5);
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->boolean('is_default')->default(false);
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->unique('code');
            });
        }

        // Create setup_collections table
        if (!Schema::hasTable('setup_collections')) {
            Schema::create('setup_collections', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('collection_key');
                $table->string('name')->nullable();
                $table->integer('sort')->default(0);
                $table->timestamps();

                $table->unique(['tenant_id', 'collection_key']);
            });
        }

        // Create setup_collection_entries table
        if (!Schema::hasTable('setup_collection_entries')) {
            Schema::create('setup_collection_entries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('setup_collection_id')->constrained()->cascadeOnDelete();
                $table->json('data')->nullable();
                $table->integer('sort')->default(0);
                $table->timestamps();
            });
        }

        // Create user_settings table
        if (!Schema::hasTable('user_settings')) {
            Schema::create('user_settings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');
                $table->string('locale', 5)->default('en');
                $table->timestamps();
            });
        }

        // Migrate existing data from users table to user_settings if columns exist and user_settings is empty
        if (Schema::hasColumn('users', 'locale') && Schema::hasTable('user_settings') && DB::table('user_settings')->count() === 0) {
            DB::table('users')->orderBy('id')->each(function ($user): void {
                DB::table('user_settings')->insert([
                    'user_id' => $user->id,
                    'locale' => $user->locale ?? 'en',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        }

        // Add noerd columns to users table if they don't exist
        $hasSelectedTenantId = Schema::hasColumn('users', 'selected_tenant_id');
        $hasSelectedApp = Schema::hasColumn('users', 'selected_app');
        $hasSuperAdmin = Schema::hasColumn('users', 'super_admin');
        $hasLocale = Schema::hasColumn('users', 'locale');
        $hasApiToken = Schema::hasColumn('users', 'api_token');

        if (!$hasSelectedTenantId || !$hasSelectedApp || !$hasSuperAdmin || !$hasLocale || !$hasApiToken) {
            Schema::table('users', function (Blueprint $table) use ($hasSelectedTenantId, $hasSelectedApp, $hasSuperAdmin, $hasLocale, $hasApiToken): void {
                if (!$hasSelectedTenantId) {
                    $table->unsignedBigInteger('selected_tenant_id')->nullable()->after('email_verified_at');
                    $table->foreign('selected_tenant_id')->references('id')->on('tenants')->onDelete('set null');
                    $table->index('selected_tenant_id');
                }
                if (!$hasSelectedApp) {
                    $table->string('selected_app')->nullable()->after('selected_tenant_id');
                }
                if (!$hasSuperAdmin) {
                    $table->boolean('super_admin')->default(false)->after('selected_app');
                }
                if (!$hasLocale) {
                    $table->string('locale', 5)->default('en')->after('super_admin');
                }
                if (!$hasApiToken) {
                    $table->string('api_token', 80)->unique()->nullable()->after('remember_token');
                }
            });
        }

        // Handle existing invoices table if it exists (rename to tenant_invoices)
        if (Schema::hasTable('invoices') && !Schema::hasTable('tenant_invoices_backup')) {
            try {
                Schema::rename('invoices', 'tenant_invoices');
            } catch (Exception $e) {
                // If rename fails, the table might already be renamed or not exist
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop in reverse order of creation
        if (Schema::hasColumn('users', 'selected_tenant_id')) {
            $driver = Schema::getConnection()->getDriverName();

            if ($driver === 'mysql' || $driver === 'mariadb') {
                // MySQL/MariaDB: Need to explicitly drop foreign key and index
                Schema::table('users', function (Blueprint $table): void {
                    $table->dropForeign(['selected_tenant_id']);
                    $table->dropIndex(['selected_tenant_id']);
                });
            }
            // SQLite: Foreign keys and indexes are dropped automatically with columns

            // Drop columns one by one for SQLite compatibility
            $columnsToDrop = ['selected_tenant_id', 'selected_app', 'super_admin', 'locale', 'api_token'];
            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('users', $column)) {
                    Schema::table('users', function (Blueprint $table) use ($column): void {
                        $table->dropColumn($column);
                    });
                }
            }
        }

        Schema::dropIfExists('user_settings');
        Schema::dropIfExists('setup_collection_entries');
        Schema::dropIfExists('setup_collections');
        Schema::dropIfExists('setup_languages');
        Schema::dropIfExists('tenant_invoices');
        Schema::dropIfExists('user_role');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('users_tenants');
        Schema::dropIfExists('tenant_app');
        Schema::dropIfExists('profiles');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('tenant_apps');
    }
};
