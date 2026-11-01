<?php

declare(strict_types=1);

namespace Noerd\Services;

use Illuminate\Support\Facades\Route;

/**
 * The navigation of the profile area — the pages every signed-in person
 * reaches through "Profile" in the user menu. The core registers its own
 * pages (profile and password, language and locale, tenant access, delete
 * account); a module adds a page of its own in its provider's boot() with
 * the route of a full-page component that renders `<x-noerd::profile-layout>`.
 * Lower `sort` first, equal sorts in registration order; an entry whose
 * route does not exist in this installation is left out.
 */
final class ProfileNavigationRegistry
{
    /** @var list<array{label: string, route: string, heroicon: ?string, sort: int}> */
    private array $entries = [];

    /**
     * @param  string  $label  English translation key
     * @param  string|null  $heroicon  outline heroicon name, e.g. `user-circle`
     */
    public function register(string $label, string $route, ?string $heroicon = null, int $sort = 100): void
    {
        $this->entries = array_values(array_filter($this->entries, fn(array $entry): bool => $entry['route'] !== $route));
        $this->entries[] = ['label' => $label, 'route' => $route, 'heroicon' => $heroicon, 'sort' => $sort];
    }

    public function has(string $route): bool
    {
        return $this->get($route) !== null;
    }

    /**
     * @return array{label: string, route: string, heroicon: ?string, sort: int}|null
     */
    public function get(string $route): ?array
    {
        foreach ($this->all() as $entry) {
            if ($entry['route'] === $route) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * The entries in render order — only those whose route exists.
     *
     * @return list<array{label: string, route: string, heroicon: ?string, sort: int}>
     */
    public function all(): array
    {
        $entries = array_values(array_filter($this->entries, fn(array $entry): bool => Route::has($entry['route'])));

        // usort is stable since PHP 8: equal sorts keep their registration order.
        usort($entries, fn(array $a, array $b): int => $a['sort'] <=> $b['sort']);

        return $entries;
    }
}
