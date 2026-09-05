<?php

declare(strict_types=1);

namespace Noerd\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A tenant app was newly assigned to a tenant. Modules listen for this to pull
 * in whatever an app of theirs needs alongside it — the core itself must not
 * know about any module, and a module must not patch the core's assignment
 * screens. Fired only for an app the tenant did not hold before.
 */
class TenantAppAssigned
{
    use Dispatchable;

    /**
     * @param  string  $appName  tenant_apps.name as stored (canonically uppercase)
     */
    public function __construct(public int $tenantId, public string $appName) {}
}
