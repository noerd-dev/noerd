<?php

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

        $tenants = NoerdAuth::user()->adminTenants;

        foreach ($tenants as $tenant) {
            $filter['options'][$tenant->id] = $tenant->name;
        }

        return $filter;
    }
}
