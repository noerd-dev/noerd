<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create user_roles table if it doesn't exist
        if (!Schema::hasTable('user_roles')) {
            Schema::create('user_roles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('key');
                $table->string('name');
                $table->string('description')->nullable();
                $table->timestamps();

                // Foreign key constraint
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

                // Index
                $table->index('tenant_id');
            });
        }

        // Create user_role pivot table if it doesn't exist
        if (!Schema::hasTable('user_role')) {
            Schema::create('user_role', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('user_role_id');
                $table->timestamps();

                // Note: Foreign key constraints are not added here since the referenced tables
                // might be in different modules. They should be handled at the application level.
                
                // Indexes for performance
                $table->index('user_id');
                $table->index('user_role_id');
                
                // Unique constraint to prevent duplicate assignments
                $table->unique(['user_id', 'user_role_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_role');
        Schema::dropIfExists('user_roles');
    }
}; 