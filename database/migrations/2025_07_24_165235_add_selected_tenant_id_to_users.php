<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'selected_tenant_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unsignedBigInteger('selected_tenant_id')->nullable()->after('email_verified_at');
                $table->foreign('selected_tenant_id')->references('id')->on('tenants')->onDelete('set null');
                $table->index('selected_tenant_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'selected_tenant_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropForeign(['selected_tenant_id']);
                $table->dropIndex(['selected_tenant_id']);
                $table->dropColumn('selected_tenant_id');
            });
        }
    }
};
