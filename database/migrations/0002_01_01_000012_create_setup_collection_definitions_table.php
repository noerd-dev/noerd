<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('setup_collection_definitions')) {
            return;
        }

        Schema::create('setup_collection_definitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('filename');
            $table->string('key');
            $table->string('title');
            $table->string('title_list');
            $table->text('description')->nullable();
            $table->json('fields');
            $table->foreignId('created_by')->nullable()->constrained('noerd_users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
            $table->unique(['tenant_id', 'filename']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setup_collection_definitions');
    }
};
