<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('noerd_settings')) {
            return;
        }

        Schema::table('noerd_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('noerd_settings', 'detail_theme')) {
                $table->string('detail_theme')->nullable();
            }

            if (! Schema::hasColumn('noerd_settings', 'detail_theme_enforced')) {
                $table->boolean('detail_theme_enforced')->default(false);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('noerd_settings')) {
            return;
        }

        Schema::table('noerd_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('noerd_settings', 'detail_theme')) {
                $table->dropColumn('detail_theme');
            }

            if (Schema::hasColumn('noerd_settings', 'detail_theme_enforced')) {
                $table->dropColumn('detail_theme_enforced');
            }
        });
    }
};
