<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Updates all tenant_apps icons that don't have a namespace (no ::)
     * to use the noerd:: namespace. For example: icons.liefertool -> noerd::icons.liefertool
     */
    public function up(): void
    {
        DB::table('tenant_apps')
            ->where('icon', 'NOT LIKE', '%::%')
            ->whereNotNull('icon')
            ->where('icon', '!=', '')
            ->update([
                'icon' => DB::raw("CONCAT('noerd::', icon)"),
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * Removes the noerd:: prefix from icons that have it.
     */
    public function down(): void
    {
        DB::table('tenant_apps')
            ->where('icon', 'LIKE', 'noerd::%')
            ->update([
                'icon' => DB::raw("REPLACE(icon, 'noerd::', '')"),
            ]);
    }
};
