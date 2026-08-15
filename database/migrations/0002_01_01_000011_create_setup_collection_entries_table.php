<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('setup_collection_entries')) {
            return;
        }

        Schema::create('setup_collection_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('setup_collection_id')->constrained('setup_collections')->cascadeOnDelete();
            $table->json('data')->nullable();
            $table->integer('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setup_collection_entries');
    }
};
