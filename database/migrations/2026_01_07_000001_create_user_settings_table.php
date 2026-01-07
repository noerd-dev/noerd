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
        // Create user_settings table
        if (! Schema::hasTable('user_settings')) {
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

        // Migrate existing data from users table if columns exist and user_settings is empty
        if (Schema::hasColumn('users', 'selected_tenant_id') && DB::table('user_settings')->count() === 0) {
            // Get valid tenant IDs to validate foreign key references
            $validTenantIds = DB::table('tenants')->pluck('id')->toArray();

            DB::table('users')->orderBy('id')->each(function ($user) use ($validTenantIds): void {
                // Only keep selected_tenant_id if it references a valid tenant
                $selectedTenantId = $user->selected_tenant_id;
                if ($selectedTenantId !== null && ! in_array($selectedTenantId, $validTenantIds)) {
                    $selectedTenantId = null;
                }

                DB::table('user_settings')->insert([
                    'user_id' => $user->id,
                    'selected_tenant_id' => $selectedTenantId,
                    'selected_app' => $user->selected_app,
                    'locale' => $user->locale ?? 'en',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            // Remove old columns from users table
            Schema::table('users', function (Blueprint $table): void {
                // Check if foreign key exists before dropping
                $foreignKeys = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_NAME = 'users' AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME LIKE '%selected_tenant_id%'");
                if (count($foreignKeys) > 0) {
                    $table->dropForeign(['selected_tenant_id']);
                }

                // Check if index exists before dropping
                $indexes = DB::select("SHOW INDEX FROM users WHERE Key_name LIKE '%selected_tenant_id%'");
                if (count($indexes) > 0) {
                    $table->dropIndex(['selected_tenant_id']);
                }
            });

            // Drop columns if they exist
            if (Schema::hasColumn('users', 'selected_tenant_id')) {
                Schema::table('users', function (Blueprint $table): void {
                    $table->dropColumn('selected_tenant_id');
                });
            }
            if (Schema::hasColumn('users', 'selected_app')) {
                Schema::table('users', function (Blueprint $table): void {
                    $table->dropColumn('selected_app');
                });
            }
            if (Schema::hasColumn('users', 'locale')) {
                Schema::table('users', function (Blueprint $table): void {
                    $table->dropColumn('locale');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add columns to users table
        if (! Schema::hasColumn('users', 'selected_tenant_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unsignedBigInteger('selected_tenant_id')->nullable()->after('email_verified_at');
                $table->string('selected_app')->nullable()->after('selected_tenant_id');
                $table->string('locale', 5)->default('en')->after('super_admin');
                $table->foreign('selected_tenant_id')->references('id')->on('tenants')->onDelete('set null');
                $table->index('selected_tenant_id');
            });

            // Migrate data back
            DB::table('user_settings')->orderBy('id')->each(function ($setting): void {
                DB::table('users')->where('id', $setting->user_id)->update([
                    'selected_tenant_id' => $setting->selected_tenant_id,
                    'selected_app' => $setting->selected_app,
                    'locale' => $setting->locale,
                ]);
            });
        }

        Schema::dropIfExists('user_settings');
    }
};
