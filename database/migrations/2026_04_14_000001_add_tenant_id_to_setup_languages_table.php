<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop existing unique constraint on code
        Schema::table('setup_languages', function (Blueprint $table): void {
            $table->dropUnique(['code']);
        });

        // 2. Add tenant_id as nullable first
        Schema::table('setup_languages', function (Blueprint $table): void {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
        });

        // 3. Seed DE + EN for each existing tenant
        $tenantIds = DB::table('tenants')->pluck('id');

        foreach ($tenantIds as $tenantId) {
            DB::table('setup_languages')->insert([
                [
                    'tenant_id' => $tenantId,
                    'code' => 'de',
                    'name' => 'Deutsch',
                    'is_active' => true,
                    'is_default' => true,
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'tenant_id' => $tenantId,
                    'code' => 'en',
                    'name' => 'English',
                    'is_active' => true,
                    'is_default' => false,
                    'sort_order' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

        // 4. Delete old rows without tenant_id
        DB::table('setup_languages')->whereNull('tenant_id')->delete();

        // 5. Make tenant_id non-nullable + add foreign key and composite unique
        Schema::table('setup_languages', function (Blueprint $table): void {
            $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
            $table->index('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique(['tenant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('setup_languages', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'code']);
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
            $table->unique('code');
        });
    }
};
