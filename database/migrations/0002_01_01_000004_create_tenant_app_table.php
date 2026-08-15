<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('tenant_app')) {
            return;
        }

        Schema::create('tenant_app', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_app_id')->constrained('tenant_apps')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->boolean('is_hidden')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_app');
    }
};
