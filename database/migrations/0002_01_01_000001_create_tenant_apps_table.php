<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('tenant_apps')) {
            return;
        }

        Schema::create('tenant_apps', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('name');
            $table->string('icon');
            $table->string('route');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_apps');
    }
};
