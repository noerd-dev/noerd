<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Remove selected_tenant_id and selected_app columns from user_settings table.
     * These values are now stored in the session via TenantSessionHelper.
     */
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            $table->dropForeign(['selected_tenant_id']);
            $table->dropIndex(['selected_tenant_id']);
            $table->dropColumn(['selected_tenant_id', 'selected_app']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('selected_tenant_id')->nullable()->after('user_id');
            $table->string('selected_app')->nullable()->after('selected_tenant_id');
            $table->foreign('selected_tenant_id')->references('id')->on('tenants')->onDelete('set null');
            $table->index('selected_tenant_id');
        });
    }
};
