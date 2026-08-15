<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('noerd_profiles')) {
            return;
        }

        Schema::create('noerd_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('key');
            $table->string('name');
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('noerd_profiles');
    }
};
