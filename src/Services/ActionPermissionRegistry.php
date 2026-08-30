<?php

declare(strict_types=1);

namespace Noerd\Services;

use InvalidArgumentException;

/**
 * Catalog of the named action permissions the installed modules declare
 * (e.g. 'production_start_run'). Modules register their actions in the
 * ServiceProvider's boot():
 *
 *     app(ActionPermissionRegistry::class)->register('production_start_run', 'Start Production Run');
 *
 * Keys are snake_case ([a-z0-9_]) — they become permission keys
 * (`action_{key}`) and Livewire property-path segments, both of which dots
 * or dashes would break.
 *
 * The registry is purely declarative: enforcement happens at the action's call
 * sites via AccessHelper::canPerformAction() (manually or through the
 * `action-permission:{key}` middleware). Authorization tooling enumerates the
 * registry — an action checked in code but never registered here stays
 * invisible to it.
 */
final class ActionPermissionRegistry
{
    /** @var array<string, string> action key => label (translation key) */
    private array $actions = [];

    public function register(string $key, string $label): void
    {
        if (! preg_match('/^[a-z0-9_]+$/', $key)) {
            throw new InvalidArgumentException(
                "Action permission key [{$key}] must be snake_case ([a-z0-9_]).",
            );
        }

        $this->actions[$key] = $label;
    }

    /**
     * @return array<string, string> action key => label, sorted by key
     */
    public function all(): array
    {
        $actions = $this->actions;
        ksort($actions);

        return $actions;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->actions);
    }

    public function label(string $key): ?string
    {
        return $this->actions[$key] ?? null;
    }
}
