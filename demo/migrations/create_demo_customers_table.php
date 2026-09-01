<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('demo_customers')) {
            return;
        }

        Schema::create('demo_customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('zipcode')->nullable();
            $table->string('city')->nullable();
            $table->text('description')->nullable();
            $table->longText('content')->nullable();
            $table->string('status')->nullable();
            $table->string('priority')->nullable();
            $table->decimal('revenue', 10, 2)->nullable();
            $table->string('brand_color', 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('contract_start')->nullable();
            $table->time('preferred_time')->nullable();
            $table->unsignedBigInteger('image_id')->nullable();
            $table->foreignId('demo_category_id')->nullable()->constrained('demo_categories')->nullOnDelete();
            $table->json('custom_attributes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_customers');
    }
};
