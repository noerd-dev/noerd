<?php

namespace Noerd\Contracts;

use Illuminate\Support\Collection;
use Noerd\Support\SetupCollectionDefinitionData;
use RuntimeException;

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
     * Persist a definition (create or update).
     * Returns the canonical filename of the saved definition.
     *
     * @throws RuntimeException when the implementation is read-only.
     */
    public function save(SetupCollectionDefinitionData $data, ?string $originalFilename = null, ?int $tenantId = null): string;

    /**
     * Duplicate an existing definition, suffixing its filename and key with "2".
     *
     * @throws RuntimeException when the implementation is read-only.
     */
    public function copy(string $filename, ?int $tenantId = null): string;

    /**
     * @throws RuntimeException when the implementation is read-only.
     */
    public function delete(string $filename, ?int $tenantId = null): void;

    /**
     * Resolve field definitions in the YAML-shaped array used by SetupCollectionHelper.
     *
     * Returns null if the definition does not exist.
     *
     * @return array{title?: string, titleList?: string, key?: string, description?: string, fields?: array<int, array<string, mixed>>}|null
     */
    public function resolveFields(string $filename): ?array;

    /**
     * Whether this implementation supports mutations (save/copy/delete).
     */
    public function isWritable(): bool;
}
