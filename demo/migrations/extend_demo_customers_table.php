<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('demo_customers', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('city');
            $table->longText('content')->nullable()->after('description');
            $table->string('status')->nullable()->after('content');
            $table->string('priority')->nullable()->after('status');
            $table->decimal('revenue', 10, 2)->nullable()->after('priority');
            $table->string('brand_color', 7)->nullable()->after('revenue');
            $table->boolean('is_active')->default(true)->after('brand_color');
            $table->date('contract_start')->nullable()->after('is_active');
            $table->time('preferred_time')->nullable()->after('contract_start');
            $table->unsignedBigInteger('image_id')->nullable()->after('preferred_time');
            $table->foreignId('demo_category_id')->nullable()->constrained('demo_categories')->nullOnDelete()->after('image_id');
            $table->json('custom_attributes')->nullable()->after('demo_category_id');
        });

        Schema::create('demo_customer_demo_tag', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('demo_customer_id')->constrained('demo_customers')->cascadeOnDelete();
            $table->foreignId('demo_tag_id')->constrained('demo_tags')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_customer_demo_tag');

        Schema::table('demo_customers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('demo_category_id');
            $table->dropColumn([
                'description', 'content', 'status', 'priority', 'revenue',
                'brand_color', 'is_active', 'contract_start', 'preferred_time',
                'image_id', 'custom_attributes',
            ]);
        });
    }
};
