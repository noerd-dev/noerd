<?php

declare(strict_types=1);

namespace Noerd\Traits;

use Illuminate\Database\Eloquent\Builder;
use Noerd\Helpers\AccessHelper;

/**
 * OPT-IN read guard at the QUERY level: while the object-read permission
 * denies the model for the current user, EVERY query on it yields nothing —
 * aggregates (count/sum), relations, hand-built dashboard counters and manual
 * listData() queries included. The generic list/detail guards only cover the
 * NoerdList/NoerdDetail render paths; this trait closes the gap for models
 * whose data also flows through bespoke queries.
 *
 * Deliberately opt-in per model: noerd never silently rewrites an
 * application's queries, and an automatic global guard would also empty
 * framework internals (users, tenants) and break sign-in for deny-all
 * profiles. Protecting an object at the query level therefore REQUIRES adding
 * this trait to its model.
 *
 * Unaffected: admins, guests and every context without an authenticated noerd
 * user (console, queue jobs, public shop pages) — canReadObject() allows them
 * all. A system read that must run in a denied user's request context can lift
 * the guard explicitly (the constant is read through the MODEL — PHP forbids
 * accessing trait constants on the trait itself):
 *
 *     Invoice::withoutGlobalScope(Invoice::OBJECT_READ_GUARD_SCOPE)
 */
trait GuardedByObjectPermission
{
    public const OBJECT_READ_GUARD_SCOPE = 'noerdObjectReadGuard';

    public static function bootGuardedByObjectPermission(): void
    {
        static::addGlobalScope(self::OBJECT_READ_GUARD_SCOPE, function (Builder $builder): void {
            // Evaluated at query-build time, so the memoized gate decides per
            // request; for allowed users the scope adds nothing.
            if (! AccessHelper::canReadObject(static::class)) {
                $builder->whereRaw('1 = 0');
            }
        });
    }
}
