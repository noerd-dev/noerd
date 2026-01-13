<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // 1. Add is_hidden column to pivot table
        if (! Schema::hasColumn('tenant_app', 'is_hidden')) {
            Schema::table('tenant_app', function (Blueprint $table): void {
                $table->boolean('is_hidden')->default(false)->after('tenant_id');
            });
        }

        // 2. Copy values from tenant_apps to tenant_app pivot
        if (Schema::hasColumn('tenant_apps', 'is_hidden')) {
            DB::statement('
                UPDATE tenant_app
                JOIN tenant_apps ON tenant_app.tenant_app_id = tenant_apps.id
                SET tenant_app.is_hidden = tenant_apps.is_hidden
            ');

            // 3. Remove is_hidden column from tenant_apps
            Schema::table('tenant_apps', function (Blueprint $table): void {
                $table->dropColumn('is_hidden');
            });
        }
    }

    public function down(): void
    {
        // Add is_hidden column back to tenant_apps
        if (! Schema::hasColumn('tenant_apps', 'is_hidden')) {
            Schema::table('tenant_apps', function (Blueprint $table): void {
                $table->boolean('is_hidden')->default(false)->after('is_active');
            });
        }

        // Copy values back (takes the first value per app)
        if (Schema::hasColumn('tenant_app', 'is_hidden')) {
            DB::statement('
                UPDATE tenant_apps
                SET is_hidden = COALESCE(
                    (SELECT is_hidden FROM tenant_app WHERE tenant_app.tenant_app_id = tenant_apps.id LIMIT 1),
                    false
                )
            ');

            // Remove is_hidden column from tenant_app pivot
            Schema::table('tenant_app', function (Blueprint $table): void {
                $table->dropColumn('is_hidden');
            });
        }
    }
};
