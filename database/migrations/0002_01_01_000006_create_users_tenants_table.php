<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('users_tenants')) {
            return;
        }

        Schema::create('users_tenants', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained('noerd_users')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('profile_key', 32)->nullable();
            $table->timestamps();

            $table->primary(['user_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users_tenants');
    }
};
