<?php

declare(strict_types=1);

namespace Noerd\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Noerd\Models\Tenant;

/**
 * A super admin is deleting a tenant. Fired inside the deletion's transaction,
 * after every membership ended and before the tenant row goes, so a module
 * removes here what the database does not cascade for it — rows whose tenant
 * column has no foreign key. A listener that throws rolls the whole deletion
 * back. Files and other resources outside the database are released on
 * TenantDeleted, once the deletion committed.
 */
class TenantDeleting
{
    use Dispatchable;

    public function __construct(public Tenant $tenant) {}
}
