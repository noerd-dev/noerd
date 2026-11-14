<?php

declare(strict_types=1);

namespace Noerd\Exceptions;

use RuntimeException;

/**
 * A signed-in user's request tried to write a tenant-scoped record into a
 * tenant other than the one it works in — a foreign tenant_id on create, or a
 * changed tenant_id on an existing record. Code that writes for another tenant
 * on purpose names it with TenantContext::run().
 */
final class TenantMismatchException extends RuntimeException
{
    public static function forCreate(string $modelClass, mixed $tenantId, ?int $currentTenantId): self
    {
        return new self(sprintf(
            '%s was created for tenant %s, but the signed-in user works in tenant %s. Write for another tenant inside TenantContext::run().',
            $modelClass,
            var_export($tenantId, true),
            var_export($currentTenantId, true),
        ));
    }

    public static function forUpdate(string $modelClass, mixed $from, mixed $to): self
    {
        return new self(sprintf(
            '%s may not move from tenant %s to tenant %s in a user request. Move it inside TenantContext::run().',
            $modelClass,
            var_export($from, true),
            var_export($to, true),
        ));
    }
}
