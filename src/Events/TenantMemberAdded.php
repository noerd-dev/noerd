<?php

declare(strict_types=1);

namespace Noerd\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * An account became a member of a tenant (Tenant::addMember()). Fired only for
 * an account that was no member before.
 */
class TenantMemberAdded
{
    use Dispatchable;

    public function __construct(public int $tenantId, public int $userId) {}
}
