<?php

declare(strict_types=1);

namespace Noerd\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A tenant app was taken away from a tenant — the counterpart of
 * TenantAppAssigned. Modules listen for this to release what an app of theirs
 * set up for the tenant; the core neither knows nor deletes any module data.
 * Fired only for an app the tenant actually held.
 */
class TenantAppRemoved
{
    use Dispatchable;

    /**
     * @param  string  $appName  tenant_apps.name as stored (canonically uppercase)
     */
    public function __construct(public int $tenantId, public string $appName) {}
}
