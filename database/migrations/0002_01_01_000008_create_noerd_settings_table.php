<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('noerd_settings')) {
            return;
        }

        Schema::create('noerd_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->string('currency')->default('EUR');
            $table->string('detail_theme')->nullable();
            $table->boolean('detail_theme_enforced')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('noerd_settings');
    }
};
