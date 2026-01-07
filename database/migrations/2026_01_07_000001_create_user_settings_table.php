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

            // Note: Old columns (selected_tenant_id, selected_app, locale) are kept in users table
            // for backwards compatibility. They are no longer used by the application.
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Migrate data back to users table if columns exist
        if (Schema::hasColumn('users', 'selected_tenant_id')) {
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
