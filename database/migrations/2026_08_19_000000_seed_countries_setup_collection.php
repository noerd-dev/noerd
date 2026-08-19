<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Noerd\Support\DefaultCountries;

/**
 * Every tenant gets the default COUNTRIES setup collection (names + ISO codes).
 * Existing entries (e.g. seeded name-only by the crm module) are backfilled
 * with their code instead of being duplicated. New tenants are covered by the
 * Tenant::created hook in NoerdServiceProvider.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('setup_collections') || ! Schema::hasTable('setup_collection_entries')) {
            return;
        }

        DB::table('tenants')->orderBy('id')->pluck('id')->each(function (int $tenantId): void {
            DefaultCountries::ensureForTenant($tenantId);
        });
    }

    public function down(): void
    {
        // Seed data stays — tenants may have edited the entries.
    }
};
