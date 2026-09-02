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
        // simply offers an empty tenant picklist instead of fataling.
        $tenants = NoerdAuth::user()?->adminTenants ?? collect();

        foreach ($tenants as $tenant) {
            $filter['options'][$tenant->id] = $tenant->name;
        }

        return $filter;
    }
}
