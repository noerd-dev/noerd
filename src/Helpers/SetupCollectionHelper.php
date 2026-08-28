<?php

namespace Noerd\Helpers;

use Noerd\Contracts\SetupCollectionDefinitionRepositoryContract;
use Noerd\Models\SetupCollection;
use Noerd\Models\SetupLanguage;

class SetupCollectionHelper
{
    public function __construct(
        private readonly SetupCollectionDefinitionRepositoryContract $repository,
    ) {}

    /**
     * Get collection field definitions.
     * Static method delegates to the container-resolved instance for mockability.
     */
    public static function getCollectionFields(?string $collection): ?array
    {
        return app(self::class)->resolveCollectionFields($collection);
    }

    /**
     * Get table columns configuration from the resolved collection definition.
     */
    public static function getCollectionTable(string $collection): array
    {
        return app(self::class)->resolveCollectionTable($collection);
    }

    /**
     * Get all available collections from the active repository.
     *
     * @return array<int, array{key: string, title: string, titleList: string, buttonList: string}>
     */
    public static function getAllCollections(): array
    {
        return app(self::class)->resolveAllCollections();
    }

    /**
     * Instance method: resolve collection fields via the repository.
     */
    public function resolveCollectionFields(?string $collection): ?array
    {
        if ($collection === null) {
            return null;
        }

        $fields = $this->repository->resolveFields($collection);

        if ($fields === null) {
            return null;
        }

        // Remove any page_id references (not used in Setup collections)
        if (isset($fields['fields'])) {
            foreach ($fields['fields'] as $key => $item) {
                if (isset($item['name']) && $item['name'] === 'collection.page_id') {
                    unset($fields['fields'][$key]);
                }
            }
            $fields['fields'] = array_values($fields['fields']);
        }

        return $fields;
    }

    /**
     * Instance method: resolve collection table configuration.
     */
    public function resolveCollectionTable(string $collection): array
    {
        $table = [];
        $collectionFields = $this->resolveCollectionFields($collection);

        if (! $collectionFields || ! isset($collectionFields['fields'])) {
            return $table;
        }

        foreach ($collectionFields['fields'] as $collectionField) {
            $tableColumn = [];

            $tableColumn['width'] = $collectionField['width'] ?? 10;
            $tableColumn['label'] = $collectionField['label'] ?? $collectionField['name'];
            $tableColumn['field'] = str_replace('detailData.', '', $collectionField['name']);
            $tableColumn['translatable'] = str_starts_with($collectionField['type'] ?? '', 'translatable');

            if ($tableColumn['field'] !== 'page_id') {
                $table[] = $tableColumn;
            }
        }

        return $table;
    }

    /**
     * Build select options from a setup collection's entries for the current
     * tenant: value = `data[valueField]` (or the entry id when no valueField is
     * given), label = `data[displayField]` resolved to the active language.
     * Shared by the `setupCollectionSelect` form element and the list picklist
     * badges, so both always agree on values and labels.
     *
     * @return array<int, array{value: mixed, label: string}>
     */
    public static function selectOptions(string $collectionKey, string $displayField = 'name', ?string $valueField = null): array
    {
        $collection = SetupCollection::where('collection_key', $collectionKey)->first();
        if (! $collection) {
            return [];
        }

        $locale = session('selectedLanguage') ?? app()->getLocale();
        $options = [];

        foreach ($collection->entries as $entry) {
            $label = $entry->data[$displayField] ?? '';
            if (is_array($label)) {
                $label = $label[$locale] ?? $label[SetupLanguage::getDefaultCode()] ?? reset($label) ?: '';
            }

            $options[] = [
                'value' => $valueField ? ($entry->data[$valueField] ?? '') : $entry->id,
                'label' => (string) $label,
            ];
        }

        return $options;
    }

    /**
     * @return array<int, array{key: string, title: string, titleList: string, buttonList: string}>
     */
    public function resolveAllCollections(): array
    {
        return $this->repository->all()
            ->map(fn($definition) => [
                'key' => $definition->filename,
                'title' => $definition->title ?: ucfirst($definition->filename),
                'titleList' => $definition->titleList ?: ucfirst($definition->filename),
                'buttonList' => __('New Entry'),
            ])
            ->all();
    }
}
