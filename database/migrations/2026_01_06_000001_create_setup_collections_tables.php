<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('setup_languages')) {
            Schema::create('setup_languages', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 5);
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->boolean('is_default')->default(false);
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->unique('code');
            });
        }

        if (! Schema::hasTable('setup_collections')) {
            Schema::create('setup_collections', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('collection_key');
                $table->string('name')->nullable();
                $table->integer('sort')->default(0);
                $table->timestamps();

                $table->unique(['tenant_id', 'collection_key']);
            });
        }

        if (! Schema::hasTable('setup_collection_entries')) {
            Schema::create('setup_collection_entries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('setup_collection_id')->constrained()->cascadeOnDelete();
                $table->json('data')->nullable();
                $table->integer('sort')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('setup_collection_entries');
        Schema::dropIfExists('setup_collections');
        Schema::dropIfExists('setup_languages');
    }
};
