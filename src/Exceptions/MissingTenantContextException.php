<?php

declare(strict_types=1);

namespace Noerd\Exceptions;

use RuntimeException;

/**
 * A tenant-scoped model was queried or created where no tenant is known — no
 * signed-in noerd user and no TenantContext. Thrown (or reported) according to
 * `noerd.tenancy.missing_context`.
 */
final class MissingTenantContextException extends RuntimeException
{
    public static function forQuery(string $modelClass): self
    {
        return new self("{$modelClass} was queried without a tenant context. Set one with TenantContext::run(), query one tenant with ofTenant() or all of them with acrossTenants().");
    }

    public static function forCreate(string $modelClass): self
    {
        return new self("{$modelClass} was created without a tenant context and without a tenant_id.");
    }
}
