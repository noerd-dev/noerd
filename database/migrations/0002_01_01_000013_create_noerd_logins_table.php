<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('noerd_logins')) {
            return;
        }

        Schema::create('noerd_logins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('noerd_users')->cascadeOnDelete();
            $table->foreignId('impersonated_by_id')->nullable()->constrained('noerd_users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('remember')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('noerd_logins');
    }
};
