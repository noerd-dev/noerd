<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The core route names moved into the `noerd.` namespace (users → noerd.users,
 * setup → noerd.setup, …) so the package no longer claims generic global names
 * a host application may own. `tenant_apps.route` stores route names, so any
 * row still pointing at an old core name is mapped to its namespaced successor.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private array $renames = [
        'setup' => 'noerd.setup',
        'tenant-apps' => 'noerd.tenant-apps',
        'users' => 'noerd.users',
        'noerd-user.detail' => 'noerd.user.detail',
        'tenants' => 'noerd.tenants',
        'tenant.detail' => 'noerd.tenant.detail',
        'create-tenant' => 'noerd.create-tenant',
        'setup-collections' => 'noerd.setup-collections',
        'setup-collection.detail' => 'noerd.setup-collection.detail',
        'setup-collection-definitions' => 'noerd.setup-collection-definitions',
        'setup-collection-definition.detail' => 'noerd.setup-collection-definition.detail',
        'setup-languages' => 'noerd.setup-languages',
        'setup-language.detail' => 'noerd.setup-language.detail',
        'system-settings' => 'noerd.system-settings',
        'noerd-apps' => 'noerd.apps',
        'component-page' => 'noerd.component-page',
        'no-tenant' => 'noerd.no-tenant',
        'noerd-user' => 'noerd.profile',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tenant_apps')) {
            return;
        }

        foreach ($this->renames as $old => $new) {
            DB::table('tenant_apps')->where('route', $old)->update(['route' => $new]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenant_apps')) {
            return;
        }

        foreach ($this->renames as $old => $new) {
            DB::table('tenant_apps')->where('route', $new)->update(['route' => $old]);
        }
    }
};
