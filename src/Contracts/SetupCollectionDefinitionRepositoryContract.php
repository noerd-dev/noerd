<?php

declare(strict_types=1);

namespace Noerd\Contracts;

use Illuminate\Support\Collection;
use Noerd\Support\SetupCollectionDefinitionData;

interface SetupCollectionDefinitionRepositoryContract
{
    /**
     * Return every collection definition available in the current scope.
     *
     * @return Collection<int, SetupCollectionDefinitionData>
     */
    public function all(?int $tenantId = null): Collection;

    /**
     * Find a definition by its filename (e.g. "expense_categories").
     */
    public function find(string $filename, ?int $tenantId = null): ?SetupCollectionDefinitionData;

    /**
     * Find a definition by its key (e.g. "EXPENSE_CATEGORIES").
     */
    public function findByKey(string $key, ?int $tenantId = null): ?SetupCollectionDefinitionData;

    public function exists(string $filename, ?int $tenantId = null): bool;

    /**
     * Resolve field definitions in the YAML-shaped array used by SetupCollectionHelper.
     *
     * Returns null if the definition does not exist.
     *
     * @return array{title?: string, titleList?: string, key?: string, description?: string, fields?: array<int, array<string, mixed>>}|null
     */
    public function resolveFields(string $filename): ?array;
}
