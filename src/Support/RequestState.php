<?php

declare(strict_types=1);

namespace Noerd\Support;

/**
 * The request-scoped memos of noerd's static helpers (selected tenant, user
 * locale, theme setting, tenant settings, resolved config paths, picklist
 * options, …). Bound as a SCOPED service: Laravel drops it before every Octane
 * request and every queued job (`forgetScopedInstances()`), so a long-running
 * worker never serves one request or job the memo of the previous one — no
 * hand-maintained flush list needed.
 *
 * The helpers keep their static API and read and write one named bucket each:
 *
 *     $memo = &RequestState::bucket('noerd.settings');
 *     return $memo[$tenantId] ??= NoerdSettings::query()->...->first();
 */
final class RequestState
{
    /** @var array<string, array<array-key, mixed>> */
    private array $buckets = [];

    /**
     * A reference to one named bucket of the current request.
     *
     * @return array<array-key, mixed>
     */
    public static function &bucket(string $name): array
    {
        $state = app(self::class);
        $state->buckets[$name] ??= [];

        return $state->buckets[$name];
    }

    public static function forget(string $name): void
    {
        unset(app(self::class)->buckets[$name]);
    }
}
