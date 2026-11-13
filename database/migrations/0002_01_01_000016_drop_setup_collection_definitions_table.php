<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Setup collection definitions are YAML files of the installation; the
 * database mode that stored them per tenant was removed. A table that still
 * holds rows is kept: an installation that used the database mode exports its
 * definitions to app-configs/setup/collections first and drops the table itself.
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
