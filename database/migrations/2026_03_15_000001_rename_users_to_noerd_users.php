<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Foreign keys that reference the users table.
     * Format: [table => constraint_name]
     */
    private array $foreignKeys = [
        'users_tenants' => 'users_restaurants_user_id_foreign',
        'user_settings' => 'user_settings_user_id_foreign',
        'customer_invoices' => 'customer_invoices_user_id_foreign',
        'gastrofix_transaction_items' => 'gastrofix_transaction_items_user_id_foreign',
        'gastrofix_transaction_sales' => 'gastrofix_transaction_sales_user_id_foreign',
        'gastrofix_transactions' => 'gastrofix_transactions_user_id_foreign',
        'redirects' => 'redirects_user_id_foreign',
        'test_mails' => 'test_mails_user_id_foreign',
        'ai_summaries' => 'ai_summaries_user_id_foreign',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Skip if already renamed or source table doesn't exist
        if (Schema::hasTable('noerd_users') || !Schema::hasTable('users')) {
            return;
        }

        // Drop all foreign keys referencing users table
        foreach ($this->foreignKeys as $table => $constraint) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) use ($constraint): void {
                    $table->dropForeign($constraint);
                });
            }
        }

        // Rename the table
        Schema::rename('users', 'noerd_users');

        // Recreate all foreign keys referencing noerd_users
        foreach ($this->foreignKeys as $table => $constraint) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table): void {
                    $table->foreign('user_id')->references('id')->on('noerd_users')->onDelete('cascade');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('noerd_users') || Schema::hasTable('users')) {
            return;
        }

        // Drop all foreign keys referencing noerd_users
        foreach ($this->foreignKeys as $table => $constraint) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table): void {
                    $table->dropForeign(['user_id']);
                });
            }
        }

        // Rename back
        Schema::rename('noerd_users', 'users');

        // Recreate foreign keys referencing users
        foreach ($this->foreignKeys as $table => $constraint) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) use ($constraint): void {
                    $table->foreign('user_id', $constraint)->references('id')->on('users')->onDelete('cascade');
                });
            }
        }
    }
};
