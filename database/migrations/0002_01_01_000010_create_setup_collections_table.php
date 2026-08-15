<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('setup_collections')) {
            return;
        }

        Schema::create('setup_collections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('collection_key');
            $table->string('name')->nullable();
            $table->integer('sort')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'collection_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setup_collections');
    }
};
