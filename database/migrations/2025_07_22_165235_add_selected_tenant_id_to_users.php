<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'selected_tenant_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('selected_tenant_id')->nullable()->after('email_verified_at');
                $table->foreign('selected_tenant_id')->references('id')->on('tenants')->onDelete('set null');
                $table->index('selected_tenant_id');
            });
            
            echo "Added selected_tenant_id column to users table.\n";
        } else {
            echo "Column selected_tenant_id already exists in users table.\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'selected_tenant_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['selected_tenant_id']);
                $table->dropIndex(['selected_tenant_id']);
                $table->dropColumn('selected_tenant_id');
            });
            
            echo "Removed selected_tenant_id column from users table.\n";
        } else {
            echo "Column selected_tenant_id does not exist in users table.\n";
        }
    }
};
