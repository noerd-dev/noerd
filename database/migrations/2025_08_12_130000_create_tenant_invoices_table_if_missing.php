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
                $table->string('number')->unique();
                $table->date('invoice_date')->nullable();
                $table->date('due_date')->nullable();
                $table->decimal('total_gross', 12, 2)->default(0);
                $table->decimal('total_net', 12, 2)->default(0);
                $table->string('status')->default('DRAFT');
                $table->json('data')->nullable();
                $table->timestamps();

                $table->index('tenant_id');
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        // non-destructive: don't drop if exists
    }
};


