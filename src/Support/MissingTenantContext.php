<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Noerd\Exceptions\MissingTenantContextException;

/**
 * What a tenant-scoped model does where no tenant is known — no signed-in
 * noerd user, no TenantContext — set by `noerd.tenancy.missing_context`:
 *
 * - `unscoped` (default): no filter, as before (console, queue and public
 *   frontends see every tenant's rows unless the code filters itself)
 * - `audit`: no filter, but the first such query per model and request is
 *   logged with the calling file — the list of call sites still to migrate
 * - `empty`: no rows (fail closed), reported to the error handler once per model
 * - `throw`: MissingTenantContextException
 *
 * Creating a record without a tenant_id follows the same mode.
 */
final class MissingTenantContext
{
    public const UNSCOPED = 'unscoped';

    public const AUDIT = 'audit';

    public const EMPTY = 'empty';

    public const THROW = 'throw';

    public static function mode(): string
    {
        $mode = config('noerd.tenancy.missing_context', self::UNSCOPED);

        return in_array($mode, [self::AUDIT, self::EMPTY, self::THROW], true) ? $mode : self::UNSCOPED;
    }

    public static function applyToQuery(Builder $builder, string $modelClass): void
    {
        match (self::mode()) {
            self::THROW => throw MissingTenantContextException::forQuery($modelClass),
            self::EMPTY => self::failClosed($builder, $modelClass),
            self::AUDIT => self::audit('query', $modelClass),
            default => null,
        };
    }

    public static function applyToCreate(string $modelClass): void
    {
        match (self::mode()) {
            self::THROW, self::EMPTY => throw MissingTenantContextException::forCreate($modelClass),
            self::AUDIT => self::audit('create', $modelClass),
            default => null,
        };
    }

    private static function failClosed(Builder $builder, string $modelClass): void
    {
        $builder->whereRaw('1 = 0');

        if (self::firstInRequest('reported', $modelClass)) {
            report(MissingTenantContextException::forQuery($modelClass));
        }
    }

    private static function audit(string $operation, string $modelClass): void
    {
        if (! self::firstInRequest($operation, $modelClass)) {
            return;
        }

        Log::warning('noerd tenancy: tenant-scoped model used without a tenant context', [
            'operation' => $operation,
            'model' => $modelClass,
            'caller' => self::caller(),
        ]);
    }

    private static function firstInRequest(string $operation, string $modelClass): bool
    {
        $seen = &RequestState::bucket('noerd.tenancy-missing-context');
        $key = $operation . '|' . $modelClass;

        if (isset($seen[$key])) {
            return false;
        }

        return $seen[$key] = true;
    }

    /**
     * The first frame outside vendor/ and the tenancy plumbing — where the
     * unscoped query was written.
     */
    private static function caller(): ?string
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 60) as $frame) {
            $file = $frame['file'] ?? null;

            if (! is_string($file)
                || str_contains($file, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)
                || str_ends_with($file, 'MissingTenantContext.php')
                || str_ends_with($file, 'TenantScope.php')
                || str_ends_with($file, 'BelongsToTenant.php')) {
                continue;
            }

            return $file . ':' . ($frame['line'] ?? 0);
        }

        return null;
    }
}
