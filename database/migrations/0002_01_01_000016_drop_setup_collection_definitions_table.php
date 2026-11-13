<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The core reads the setup collection definitions from YAML files; the database
 * mode that stores them per tenant is no longer part of it. A table that holds
 * rows is kept — the package providing the database mode keeps reading it, and
 * an installation without that package exports its definitions first.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('setup_collection_definitions')) {
            return;
        }

        if (DB::table('setup_collection_definitions')->exists()) {
            return;
        }

        Schema::drop('setup_collection_definitions');
    }

    public function down(): void
    {
        // The table belonged to a removed feature and is not restored.
    }
};
