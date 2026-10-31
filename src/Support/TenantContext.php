<?php

declare(strict_types=1);

namespace Noerd\Support;

use Closure;

/**
 * The tenant the current request, job or command works in — independent of a
 * signed-in user. A backend request resolves the tenant from the noerd user
 * (TenantHelper::currentTenantId() falls back to it); everything else sets it
 * explicitly: a public frontend after resolving its tenant, a queued job from
 * its payload (propagated at dispatch), a command per tenant with run().
 * `system()` is the explicit "all tenants" mode.
 *
 * Bound as a SCOPED service, so no request or job inherits the previous one's
 * tenant.
 */
final class TenantContext
{
    private ?int $tenantId = null;

    private bool $system = false;

    /** @var list<array{0: ?int, 1: bool}> contexts a running job replaced */
    private array $jobStack = [];

    public static function current(): ?int
    {
        return app(self::class)->tenantId;
    }

    public static function set(?int $tenantId): void
    {
        app(self::class)->tenantId = $tenantId;
    }

    /**
     * Forget tenant, system mode and job stack — the request ended. Octane and
     * the queue drop the scoped instance anyway; this keeps a test process
     * (one container for many requests) as fresh as production.
     */
    public static function reset(): void
    {
        $context = app(self::class);
        $context->tenantId = null;
        $context->system = false;
        $context->jobStack = [];
    }

    public static function isSystem(): bool
    {
        return app(self::class)->system;
    }

    /**
     * Run the callback in the given tenant and restore the previous context.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function run(int $tenantId, Closure $callback): mixed
    {
        $context = app(self::class);
        [$tenantBefore, $systemBefore] = [$context->tenantId, $context->system];

        $context->tenantId = $tenantId;
        $context->system = false;

        try {
            return $callback();
        } finally {
            [$context->tenantId, $context->system] = [$tenantBefore, $systemBefore];
        }
    }

    /**
     * A queued job starts: its dispatcher's tenant (the payload key written by
     * the queue payload hook) becomes the context until the job ends.
     */
    public static function enterJob(?int $tenantId): void
    {
        $context = app(self::class);
        $context->jobStack[] = [$context->tenantId, $context->system];

        if ($tenantId !== null) {
            $context->tenantId = $tenantId;
            $context->system = false;
        }
    }

    /**
     * The job ended (processed, failed or threw): restore what it replaced — a
     * job run synchronously inside a request hands the request its context back.
     */
    public static function leaveJob(): void
    {
        $context = app(self::class);
        $previous = array_pop($context->jobStack);

        if ($previous !== null) {
            [$context->tenantId, $context->system] = $previous;
        }
    }

    /**
     * Run the callback across all tenants — the tenant scope filters nothing —
     * and restore the previous context.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function system(Closure $callback): mixed
    {
        $context = app(self::class);
        $systemBefore = $context->system;

        $context->system = true;

        try {
            return $callback();
        } finally {
            $context->system = $systemBefore;
        }
    }
}
