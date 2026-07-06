<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('tenants', 'logo')) {
            Schema::table('tenants', function (Blueprint $table): void {
                $table->string('logo')->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tenants', 'logo')) {
            Schema::table('tenants', function (Blueprint $table): void {
                $table->dropColumn('logo');
            });
        }
    }
};
