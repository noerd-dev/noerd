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
                $table->boolean('is_public')->default(false);
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

        // Create noerd_profiles table (before users_tenants because of foreign key)
        if (!Schema::hasTable('noerd_profiles')) {
            Schema::create('noerd_profiles', function (Blueprint $table): void {
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

        // Create noerd_users table
        if (!Schema::hasTable('noerd_users')) {
            Schema::create('noerd_users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->unsignedBigInteger('selected_tenant_id')->nullable();
                $table->string('selected_app')->nullable();
                $table->boolean('super_admin')->default(false);
                $table->rememberToken();
                $table->string('api_token', 80)->unique()->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->timestamps();

                $table->foreign('selected_tenant_id')->references('id')->on('tenants')->onDelete('set null');
                $table->index('selected_tenant_id');
            });
        }

        // Create users_tenants table (pivot table) - only if noerd_users table exists
        if (!Schema::hasTable('users_tenants') && Schema::hasTable('noerd_users')) {
            Schema::create('users_tenants', function (Blueprint $table): void {
                $table->foreignId('user_id')->constrained('noerd_users')->onDelete('cascade');
                $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
                $table->foreignId('profile_id')->nullable()->constrained('noerd_profiles');
                $table->timestamps();
            });
        }

        // Create user_roles table
        if (!Schema::hasTable('noerd_user_roles')) {
            Schema::create('noerd_user_roles', function (Blueprint $table): void {
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
        if (!Schema::hasTable('noerd_user_role')) {
            Schema::create('noerd_user_role', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('noerd_user_role_id');
                $table->timestamps();

                // Note: Foreign key constraints are not added here since the referenced tables
                // might be in different modules. They should be handled at the application level.

                // Indexes for performance
                $table->index('user_id');
                $table->index('noerd_user_role_id');

                // Unique constraint to prevent duplicate assignments
                $table->unique(['user_id', 'noerd_user_role_id']);
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

        // Create user_settings table - only if noerd_users table exists
        if (!Schema::hasTable('noerd_user_settings') && Schema::hasTable('noerd_users')) {
            Schema::create('noerd_user_settings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('noerd_users')->onDelete('cascade');
                $table->unsignedBigInteger('selected_tenant_id')->nullable();
                $table->foreign('selected_tenant_id')->references('id')->on('tenants')->onDelete('set null');
                $table->index('selected_tenant_id');
                $table->string('locale', 5)->default('en');
                $table->timestamps();
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
        Schema::dropIfExists('noerd_user_settings');
        Schema::dropIfExists('noerd_users');
        Schema::dropIfExists('setup_collection_entries');
        Schema::dropIfExists('setup_collections');
        Schema::dropIfExists('setup_languages');
        Schema::dropIfExists('tenant_invoices');
        Schema::dropIfExists('noerd_user_role');
        Schema::dropIfExists('noerd_user_roles');
        Schema::dropIfExists('users_tenants');
        Schema::dropIfExists('tenant_app');
        Schema::dropIfExists('noerd_profiles');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('tenant_apps');
    }
};
