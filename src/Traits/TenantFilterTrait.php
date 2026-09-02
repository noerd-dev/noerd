<?php

declare(strict_types=1);

namespace Noerd\Traits;

use Noerd\Helpers\NoerdAuth;

trait TenantFilterTrait
{
    protected function getTenantsListFilter(): array
    {
        $filter['label'] = __('Tenant');
        $filter['column'] = 'tenant_id';
        $filter['type'] = 'Picklist';
        $filter['options'] = [];

        // Guest-safe: a list rendered without an authenticated noerd user
        // simply offers an empty tenant picklist instead of fataling. The
        // picklist mirrors the list scope: every tenant for a super admin,
        // the administered tenants for a tenant admin.
        $tenants = NoerdAuth::user()?->administeredTenants() ?? collect();

        foreach ($tenants as $tenant) {
            $filter['options'][$tenant->id] = $tenant->name;
        }

        return $filter;
    }
}
