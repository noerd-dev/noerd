<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * This migration ensures all noerd tables exist.
 * It's needed because these tables were consolidated into the main noerd migration
 * after some databases had already run the original migration.
 */
return new class () extends Migration {
    public function up(): void
    {
        // Add is_hidden column to tenant_apps if it doesn't exist
        if (Schema::hasTable('tenant_apps') && !Schema::hasColumn('tenant_apps', 'is_hidden')) {
            Schema::table('tenant_apps', function (Blueprint $table): void {
                $table->boolean('is_hidden')->default(false)->after('is_active');
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
                $table->unsignedBigInteger('selected_tenant_id')->nullable();
                $table->string('selected_app')->nullable();
                $table->string('locale', 5)->default('en');
                $table->timestamps();

                $table->foreign('selected_tenant_id')->references('id')->on('tenants')->onDelete('set null');
                $table->index('selected_tenant_id');
            });
        }

        // Migrate existing data from users table to user_settings if columns exist and user_settings is empty
        if (Schema::hasColumn('users', 'selected_tenant_id') && Schema::hasTable('user_settings') && DB::table('user_settings')->count() === 0) {
            $validTenantIds = DB::table('tenants')->pluck('id')->toArray();

            DB::table('users')->orderBy('id')->each(function ($user) use ($validTenantIds): void {
                $selectedTenantId = $user->selected_tenant_id;
                if ($selectedTenantId !== null && !in_array($selectedTenantId, $validTenantIds)) {
                    $selectedTenantId = null;
                }

                DB::table('user_settings')->insert([
                    'user_id' => $user->id,
                    'selected_tenant_id' => $selectedTenantId,
                    'selected_app' => $user->selected_app ?? null,
                    'locale' => $user->locale ?? 'en',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        }
    }

    public function down(): void
    {
        // Don't drop tables in down() as they may contain data
        // and the main noerd migration handles cleanup
    }
};
