<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('noerd_users') || ! Schema::hasTable('users')) {
            return;
        }

        $foreignKeys = $this->getForeignKeysReferencingTable('users');

        // Drop all foreign keys referencing users table
        foreach ($foreignKeys as $fk) {
            Schema::table($fk->TABLE_NAME, function (Blueprint $table) use ($fk): void {
                $table->dropForeign($fk->CONSTRAINT_NAME);
            });
        }

        Schema::rename('users', 'noerd_users');

        // Recreate all foreign keys pointing to noerd_users
        foreach ($foreignKeys as $fk) {
            Schema::table($fk->TABLE_NAME, function (Blueprint $table) use ($fk): void {
                $table->foreign($fk->COLUMN_NAME)->references('id')->on('noerd_users')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('noerd_users') || Schema::hasTable('users')) {
            return;
        }

        $foreignKeys = $this->getForeignKeysReferencingTable('noerd_users');

        // Drop all foreign keys referencing noerd_users
        foreach ($foreignKeys as $fk) {
            Schema::table($fk->TABLE_NAME, function (Blueprint $table) use ($fk): void {
                $table->dropForeign($fk->CONSTRAINT_NAME);
            });
        }

        Schema::rename('noerd_users', 'users');

        // Recreate all foreign keys pointing to users
        foreach ($foreignKeys as $fk) {
            Schema::table($fk->TABLE_NAME, function (Blueprint $table) use ($fk): void {
                $table->foreign($fk->COLUMN_NAME)->references('id')->on('users')->onDelete('cascade');
            });
        }
    }
    /**
     * Discover all foreign keys referencing a given table.
     *
     * @return array<int, object{TABLE_NAME: string, CONSTRAINT_NAME: string, COLUMN_NAME: string}>
     */
    private function getForeignKeysReferencingTable(string $tableName): array
    {
        return DB::select("
            SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE REFERENCED_TABLE_NAME = ?
            AND REFERENCED_TABLE_SCHEMA = DATABASE()
        ", [$tableName]);
    }
};
