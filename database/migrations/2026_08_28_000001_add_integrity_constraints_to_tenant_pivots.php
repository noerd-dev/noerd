<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Integrity constraints the pivot tables were missing: without them the same
 * user could be attached to the same tenant N times (duplicating every
 * users()/tenants() result) and the same app assigned to a tenant twice.
 * Existing duplicate rows are collapsed first, so the constraints always apply.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->constrainUsersTenants();
        $this->constrainTenantApp();
        $this->indexTenantAppsName();
    }

    public function down(): void
    {
        if (Schema::hasTable('users_tenants') && Schema::hasIndex('users_tenants', ['user_id', 'tenant_id'])) {
            Schema::table('users_tenants', function (Blueprint $table): void {
                $table->dropPrimary(['user_id', 'tenant_id']);
            });
        }

        if (Schema::hasTable('tenant_app') && Schema::hasIndex('tenant_app', 'tenant_app_tenant_app_id_tenant_id_unique')) {
            Schema::table('tenant_app', function (Blueprint $table): void {
                $table->dropUnique(['tenant_app_id', 'tenant_id']);
            });
        }

        if (Schema::hasTable('tenant_apps')) {
            Schema::table('tenant_apps', function (Blueprint $table): void {
                if (Schema::hasIndex('tenant_apps', 'tenant_apps_name_unique')) {
                    $table->dropUnique(['name']);
                }
                if (Schema::hasIndex('tenant_apps', 'tenant_apps_name_index')) {
                    $table->dropIndex(['name']);
                }
            });
        }
    }

    private function constrainUsersTenants(): void
    {
        if (! Schema::hasTable('users_tenants') || Schema::hasIndex('users_tenants', ['user_id', 'tenant_id'])) {
            return;
        }

        // The table has no primary key, so duplicates are collapsed portably:
        // read, unique by (user, tenant) keeping the newest row, rewrite.
        $rows = DB::table('users_tenants')->orderBy('created_at')->get();
        $unique = $rows->keyBy(fn($row) => $row->user_id . '|' . $row->tenant_id);

        if ($rows->count() !== $unique->count()) {
            DB::transaction(function () use ($unique): void {
                DB::table('users_tenants')->delete();
                DB::table('users_tenants')->insert(
                    $unique->values()->map(fn($row) => (array) $row)->all(),
                );
            });
        }

        Schema::table('users_tenants', function (Blueprint $table): void {
            $table->primary(['user_id', 'tenant_id']);
        });
    }

    private function constrainTenantApp(): void
    {
        if (! Schema::hasTable('tenant_app') || Schema::hasIndex('tenant_app', ['tenant_app_id', 'tenant_id'])) {
            return;
        }

        // Keep the oldest row per (app, tenant) pair.
        $keep = DB::table('tenant_app')
            ->selectRaw('MIN(id) as id')
            ->groupBy('tenant_app_id', 'tenant_id')
            ->pluck('id');

        DB::table('tenant_app')->whereNotIn('id', $keep)->delete();

        Schema::table('tenant_app', function (Blueprint $table): void {
            $table->unique(['tenant_app_id', 'tenant_id']);
        });
    }

    private function indexTenantAppsName(): void
    {
        if (! Schema::hasTable('tenant_apps')
            || Schema::hasIndex('tenant_apps', 'tenant_apps_name_unique')
            || Schema::hasIndex('tenant_apps', 'tenant_apps_name_index')) {
            return;
        }

        // `name` is the app identity (the app-configs/{name} folder key). A
        // unique index enforces that — but an installation that already carries
        // duplicates must not be bricked by an update, so it degrades to a
        // plain index there (the install commands prevent new duplicates).
        try {
            Schema::table('tenant_apps', function (Blueprint $table): void {
                $table->unique('name');
            });
        } catch (Throwable) {
            Schema::table('tenant_apps', function (Blueprint $table): void {
                $table->index('name');
            });
        }
    }
};
