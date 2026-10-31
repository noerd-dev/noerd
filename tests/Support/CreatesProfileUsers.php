<?php

declare(strict_types=1);

namespace Noerd\Tests\Support;

use Noerd\Enums\Profile;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;

/**
 * The fixture of the profile-baseline and permission tests: a user on a fresh
 * tenant under a profile, with NO app at all — the baseline is proven without
 * one. Internal to this package's suite; a module test starts from
 * createNoerdUserForApp(), which takes the profile as its third argument.
 */
trait CreatesProfileUsers
{
    /**
     * A user attached to a fresh tenant under the given profile, with that
     * tenant selected. Pass null to attach the user without any profile.
     */
    protected function createNoerdUserWithProfile(?Profile $profile): NoerdUser
    {
        $tenant = Tenant::factory()->create();

        $user = NoerdUser::factory()->create();
        $user->tenants()->attach($tenant->id, ['profile_key' => $profile?->value]);

        TenantHelper::setSelectedTenantId($tenant->id);

        return $user;
    }
}
