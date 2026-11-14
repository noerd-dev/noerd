<?php

declare(strict_types=1);

namespace Noerd\Testing\Concerns;

use Noerd\Enums\Profile;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\SetupLanguage;
use Noerd\Models\Tenant;

/**
 * An admin of a fresh tenant with the setup app selected — the fixture of a
 * test of the setup screens. The trait brings no database concern: the test
 * binds RefreshDatabase (or its own) next to it.
 *
 *     uses(TestCase::class, RefreshDatabase::class, CreatesSetupUser::class);
 */
trait CreatesSetupUser
{
    /**
     * @return array{user: NoerdUser, tenant: Tenant}
     */
    protected function createUserWithSetupAccess(): array
    {
        $tenant = Tenant::factory()->create();
        SetupLanguage::ensureDefaultLanguagesForTenant($tenant->id);

        $user = NoerdUser::factory()->create([
            'selected_tenant_id' => $tenant->id,
        ]);
        $tenant->addMember($user, Profile::Admin->value);

        TenantHelper::setSelectedTenantId($tenant->id);
        TenantHelper::setSelectedApp('SETUP');

        return ['user' => $user, 'tenant' => $tenant];
    }
}
