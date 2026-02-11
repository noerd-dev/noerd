<?php

namespace Noerd\Traits;

use Illuminate\Support\Facades\Auth;

trait TenantFilterTrait
{
    protected function getTenantsListFilter(): array
    {
        $filter['label'] = __('noerd_label_tenant');
        $filter['column'] = 'tenant_id';
        $filter['type'] = 'Picklist';
        $filter['options'] = [];

        $tenants = Auth::user()->adminTenants;

        foreach ($tenants as $tenant) {
            $filter['options'][$tenant->id] = $tenant->name;
        }

        return $filter;
    }
}
