<?php

declare(strict_types=1);

namespace Noerd\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A tenant was deleted and the deletion committed. Modules release what lives
 * outside the database here (stored files, caches); the tenant row and every
 * table with a cascading foreign key are already gone.
 */
class TenantDeleted
{
    use Dispatchable;

    public function __construct(public int $tenantId, public string $tenantName) {}
}
