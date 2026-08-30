<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Profiles are a fixed technical concept — hardcoded as Noerd\Enums\Profile,
 * never created or deleted at runtime. The per-tenant noerd_profiles rows are
 * therefore replaced by a plain users_tenants.profile_key column: existing
 * assignments are converted via the rows' keys, then the table is dropped.
 * Fresh installs create profile_key directly and skip all of this.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('users_tenants')) {
            return;
        }

        if (! Schema::hasColumn('users_tenants', 'profile_key')) {
            Schema::table('users_tenants', function (Blueprint $table): void {
                $table->string('profile_key', 32)->nullable();
            });
        }

        if (Schema::hasColumn('users_tenants', 'profile_id')) {
            if (Schema::hasTable('noerd_profiles')) {
                DB::table('users_tenants')
                    ->join('noerd_profiles', 'noerd_profiles.id', '=', 'users_tenants.profile_id')
                    ->update(['users_tenants.profile_key' => DB::raw('noerd_profiles.key')]);
            }

            Schema::table('users_tenants', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('profile_id');
            });
        }

        Schema::dropIfExists('noerd_profiles');
    }

    public function down(): void
    {
        // The profile rows are gone for good — recreating them would invent
        // data. The key column simply stays.
    }
};
