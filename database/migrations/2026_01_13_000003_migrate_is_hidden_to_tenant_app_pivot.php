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
            DB::table('tenant_app')
                ->join('tenant_apps', 'tenant_app.tenant_app_id', '=', 'tenant_apps.id')
                ->where('tenant_apps.is_hidden', true)
                ->update(['tenant_app.is_hidden' => true]);

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
            $hiddenAppIds = DB::table('tenant_app')
                ->where('is_hidden', true)
                ->distinct()
                ->pluck('tenant_app_id');

            if ($hiddenAppIds->isNotEmpty()) {
                DB::table('tenant_apps')
                    ->whereIn('id', $hiddenAppIds)
                    ->update(['is_hidden' => true]);
            }

            // Remove is_hidden column from tenant_app pivot
            Schema::table('tenant_app', function (Blueprint $table): void {
                $table->dropColumn('is_hidden');
            });
        }
    }
};
