<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('noerd_user_settings')) {
            return;
        }

        Schema::create('noerd_user_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('noerd_users')->cascadeOnDelete();
            $table->unsignedBigInteger('selected_tenant_id')->nullable();
            $table->string('locale', 5)->default('en');
            $table->timestamps();

            $table->foreign('selected_tenant_id')->references('id')->on('tenants')->onDelete('set null');
            $table->index('selected_tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('noerd_user_settings');
    }
};
