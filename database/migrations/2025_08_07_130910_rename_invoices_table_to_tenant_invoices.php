<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            Schema::rename('invoices', 'tenant_invoices');
        } catch (Exception $e) {
        }

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('tenant_invoices', 'invoices');
    }
};
