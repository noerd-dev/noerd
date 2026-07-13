<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Remove the UI Library tenant app since the feature was removed from the noerd module.
     */
    public function up(): void
    {
        if (! Schema::hasTable('tenant_apps')) {
            return;
        }

        $appIds = DB::table('tenant_apps')->where('name', 'UI-LIBRARY')->pluck('id');

        if ($appIds->isEmpty()) {
            return;
        }

        if (Schema::hasTable('tenant_app')) {
            DB::table('tenant_app')->whereIn('tenant_app_id', $appIds)->delete();
        }

        DB::table('tenant_apps')->whereIn('id', $appIds)->delete();
    }

    /**
     * The UI Library app cannot be restored — the feature no longer exists.
     */
    public function down(): void {}
};
