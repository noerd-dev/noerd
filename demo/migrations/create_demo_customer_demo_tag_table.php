<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('demo_customer_demo_tag')) {
            return;
        }

        Schema::create('demo_customer_demo_tag', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('demo_customer_id')->constrained('demo_customers')->cascadeOnDelete();
            $table->foreignId('demo_tag_id')->constrained('demo_tags')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_customer_demo_tag');
    }
};
