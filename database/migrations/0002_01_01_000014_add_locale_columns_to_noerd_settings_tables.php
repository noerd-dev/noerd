<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The document locale of a tenant (PDFs, receipts, customer e-mails) and the
 * formatting locale of a user (backend UI). Additive and idempotent: installed
 * hosts run `php artisan migrate`, fresh installs get the columns right after
 * the create migrations.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('noerd_settings') && ! Schema::hasColumn('noerd_settings', 'locale')) {
            Schema::table('noerd_settings', function (Blueprint $table): void {
                $table->string('locale', 10)->nullable()->after('currency');
            });
        }

        if (Schema::hasTable('noerd_user_settings') && ! Schema::hasColumn('noerd_user_settings', 'format_locale')) {
            Schema::table('noerd_user_settings', function (Blueprint $table): void {
                $table->string('format_locale', 10)->nullable()->after('locale');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('noerd_settings', 'locale')) {
            Schema::table('noerd_settings', function (Blueprint $table): void {
                $table->dropColumn('locale');
            });
        }

        if (Schema::hasColumn('noerd_user_settings', 'format_locale')) {
            Schema::table('noerd_user_settings', function (Blueprint $table): void {
                $table->dropColumn('format_locale');
            });
        }
    }
};
