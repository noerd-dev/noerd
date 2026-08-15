<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('noerd_users')) {
            return;
        }

        Schema::create('noerd_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->unsignedBigInteger('selected_tenant_id')->nullable();
            $table->string('selected_app')->nullable();
            $table->boolean('super_admin')->default(false);
            $table->rememberToken();
            $table->string('api_token', 80)->unique()->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->foreign('selected_tenant_id')->references('id')->on('tenants')->onDelete('set null');
            $table->index('selected_tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('noerd_users');
    }
};
