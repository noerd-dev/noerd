<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('tenant_invoices')) {
            Schema::create('tenant_invoices', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('number');
                $table->longText('lines')->nullable();
                $table->string('customer_name')->nullable();
                $table->string('hash')->unique();
                $table->date('date')->nullable();
                $table->date('due_date')->nullable();
                $table->boolean('paid')->default(false);
                $table->decimal('total_gross_amount', 12, 2)->default(0);
                $table->timestamps();

                $table->index('tenant_id');
                $table->unique('number');
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        } else {
            // Ensure required columns exist (non-destructive adjustments)
            Schema::table('tenant_invoices', function (Blueprint $table): void {
                if (!Schema::hasColumn('tenant_invoices', 'tenant_id')) {
                    $table->unsignedBigInteger('tenant_id')->after('id');
                }
                if (!Schema::hasColumn('tenant_invoices', 'number')) {
                    $table->string('number')->nullable();
                }
                if (!Schema::hasColumn('tenant_invoices', 'lines')) {
                    $table->longText('lines')->nullable();
                }
                if (!Schema::hasColumn('tenant_invoices', 'customer_name')) {
                    $table->string('customer_name')->nullable();
                }
                if (!Schema::hasColumn('tenant_invoices', 'hash')) {
                    $table->string('hash')->nullable();
                }
                if (!Schema::hasColumn('tenant_invoices', 'date')) {
                    $table->date('date')->nullable();
                }
                if (!Schema::hasColumn('tenant_invoices', 'due_date')) {
                    $table->date('due_date')->nullable();
                }
                if (!Schema::hasColumn('tenant_invoices', 'paid')) {
                    $table->boolean('paid')->default(false);
                }
                if (!Schema::hasColumn('tenant_invoices', 'total_gross_amount')) {
                    $table->decimal('total_gross_amount', 12, 2)->default(0);
                }
            });
        }
    }

    public function down(): void
    {
        // non-destructive: don't drop if exists
    }
};
