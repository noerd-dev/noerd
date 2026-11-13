<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The interface language a new user of the tenant starts with (System Settings
 * → Default Language). Null keeps the tenant's default setup language.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('noerd_settings') && ! Schema::hasColumn('noerd_settings', 'default_language')) {
            Schema::table('noerd_settings', function (Blueprint $table): void {
                $table->string('default_language', 10)->nullable()->after('locale');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('noerd_settings') && Schema::hasColumn('noerd_settings', 'default_language')) {
            Schema::table('noerd_settings', function (Blueprint $table): void {
                $table->dropColumn('default_language');
            });
        }
    }
};
