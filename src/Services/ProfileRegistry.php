<?php

declare(strict_types=1);

namespace Noerd\Services;

use Closure;
use Noerd\Enums\Profile;

/**
 * Catalog of the selectable user profiles: the three built-in Profile enum
 * cases plus whatever a module registers in its ServiceProvider's boot():
 *
 *     app(ProfileRegistry::class)->register('MY_PROFILE', fn(): string => __('My Profile'));
 *
 * The profile pickers (user detail, tenant-access display) render from this
 * registry, so a registered profile becomes assignable without any core
 * change. A registered profile's SEMANTICS come from the authorization gates
 * (see AccessHelper) — the core's own baseline treats unknown keys like User.
 */
final class ProfileRegistry
{
    /** @var array<string, string|Closure(): string> profile key => label (or lazy label) */
    private array $profiles = [];

    private bool $seeded = false;

    public function register(string $key, string|Closure $label): void
    {
        $this->seedBuiltIns();

        $this->profiles[$key] = $label;
    }

    /**
     * Select options: profile key => translated label, built-ins first, then
     * registered profiles in registration order.
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        $this->seedBuiltIns();

        $options = [];
        foreach ($this->profiles as $key => $label) {
            $options[$key] = $label instanceof Closure ? $label() : __($label);
        }

        return $options;
    }

    public function label(string $key): ?string
    {
        return $this->options()[$key] ?? null;
    }

    private function seedBuiltIns(): void
    {
        if ($this->seeded) {
            return;
        }

        $this->seeded = true;
        foreach (Profile::cases() as $profile) {
            $this->profiles[$profile->value] = fn(): string => $profile->label();
        }
    }
}
