<?php

declare(strict_types=1);

namespace Noerd\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * An account lost its membership in a tenant (Tenant::removeMember()). The
 * account itself stays. Modules listen for this to drop what they granted the
 * account INSIDE that tenant (roles, individual grants), so a later membership
 * starts clean. Fired only when a membership was actually removed.
 */
class TenantMemberRemoved
{
    use Dispatchable;

    public function __construct(public int $tenantId, public int $userId) {}
}
