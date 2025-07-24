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
        // Create tenant_apps table
        if (!Schema::hasTable('tenant_apps')) {
            Schema::create('tenant_apps', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('name');
                $table->string('icon');
                $table->string('route');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Create tenants table
        if (!Schema::hasTable('tenants')) {
            Schema::create('tenants', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('hash')->unique();
                $table->string('module')->nullable();
                $table->string('period')->default('MONTHLY');
                $table->timestamp('last_change_at')->nullable();
                $table->boolean('demo_user')->default(true);
                $table->dateTime('demo_until')->nullable();
                $table->string('domain')->nullable();
                $table->string('logo')->nullable();
                $table->string('icon')->default('https://liefertool.de/svg/liefertool.svg');
                $table->string('from_email')->nullable();
                $table->dateTime('last_invoice')->nullable();
                $table->integer('package')->default(1);
                $table->boolean('lost')->default(false);
                $table->string('reply_email')->nullable();
                $table->boolean('module_gastrofix')->default(false);
                $table->integer('delivery_gastrofix_table')->nullable();
                $table->integer('order_counter')->default(0);
                $table->decimal('tax_percentage', 8, 2)->default(19.00);
                $table->boolean('free')->default(false);
                $table->integer('invoice_number')->default(0);
                $table->string('api_token')->nullable();
                $table->boolean('obligatory_modal')->default(false);
                $table->timestamps();
            });
        }

        // Create profiles table (before users_tenants because of foreign key)
        if (!Schema::hasTable('profiles')) {
            Schema::create('profiles', function (Blueprint $table) {
                $table->id();
                $table->string('key');
                $table->string('name');
                $table->foreignId('tenant_id')->constrained('tenants');
                $table->timestamps();
            });
        }

        // Create tenant_app table (pivot table)
        if (!Schema::hasTable('tenant_app')) {
            Schema::create('tenant_app', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_app_id')->constrained('tenant_apps')->onDelete('cascade');
                $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
                $table->timestamps();
            });
        }

        // Create users_tenants table (pivot table)
        if (!Schema::hasTable('users_tenants')) {
            Schema::create('users_tenants', function (Blueprint $table) {
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
                $table->foreignId('profile_id')->nullable()->constrained('profiles');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users_tenants');
        Schema::dropIfExists('tenant_app');
        Schema::dropIfExists('profiles');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('tenant_apps');
    }
};
