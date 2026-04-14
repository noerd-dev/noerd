<?php

declare(strict_types=1);

namespace Noerd\Tests\Traits;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Profile;
use Noerd\Models\SetupLanguage;
use Noerd\Models\Tenant;

trait CreatesSetupUser
{
    use RefreshDatabase;

    /**
     * @return array{user: NoerdUser, tenant: Tenant}
     */
    protected function createUserWithSetupAccess(): array
    {
        $tenant = Tenant::factory()->create();
        SetupLanguage::ensureDefaultLanguagesForTenant($tenant->id);

        $adminProfile = Profile::factory()->create([
            'tenant_id' => $tenant->id,
            'key' => 'ADMIN',
            'name' => 'Admin',
        ]);

        $user = NoerdUser::factory()->create([
            'selected_tenant_id' => $tenant->id,
        ]);
        $user->tenants()->attach($tenant->id, ['profile_id' => $adminProfile->id]);

        TenantHelper::setSelectedTenantId($tenant->id);
        TenantHelper::setSelectedApp('SETUP');

        return ['user' => $user, 'tenant' => $tenant];
    }
}
