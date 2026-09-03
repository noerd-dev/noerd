<?php

declare(strict_types=1);

namespace Noerd\Traits;

use Noerd\Helpers\NoerdAuth;

trait TenantFilterTrait
{
    /**
     * @return array{label: string, column: string, type: string, options: array<int, string>}
     */
    protected function getTenantsListFilter(): array
    {
        // Guest-safe: a list rendered without an authenticated noerd user
        // simply offers an empty tenant picklist instead of fataling. The
        // picklist mirrors the list scope: every tenant for a super admin,
        // the administered tenants for a tenant admin.
        $tenants = NoerdAuth::user()?->administeredTenants() ?? collect();

        return [
            'label' => __('Tenant'),
            'column' => 'tenant_id',
            'type' => 'Picklist',
            'options' => $tenants->pluck('name', 'id')->all(),
        ];
    }
}
