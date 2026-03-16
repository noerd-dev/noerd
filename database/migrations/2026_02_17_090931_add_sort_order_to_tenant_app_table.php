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
        Schema::table('tenant_app', function (Blueprint $table): void {
            if (Schema::hasColumn('tenant_app', 'is_hidden')) {
                $table->integer('sort_order')->default(0)->after('is_hidden');
            } else {
                $table->integer('sort_order')->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_app', function (Blueprint $table): void {
            $table->dropColumn('sort_order');
        });
    }
};
