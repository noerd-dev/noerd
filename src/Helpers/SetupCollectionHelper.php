<?php

namespace Noerd\Helpers;

use Exception;
use Symfony\Component\Yaml\Yaml;

class SetupCollectionHelper
{
    private const COLLECTIONS_PATH = 'app-configs/setup/collections';

    /**
     * Get collection field definitions from YAML
     */
    public static function getCollectionFields(?string $collection): ?array
    {
        if ($collection === null) {
            return null;
        }

        try {
            $path = base_path(self::COLLECTIONS_PATH . '/' . $collection . '.yml');
            $content = file_get_contents($path);
        } catch (Exception) {
            return null;
        }

        $fields = Yaml::parse($content ?: '');

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
     * Get table columns configuration from collection YAML
     */
    public static function getCollectionTable(string $collection): array
    {
        $table = [];
        $collectionFields = self::getCollectionFields($collection);

        if (! $collectionFields || ! isset($collectionFields['fields'])) {
            return $table;
        }

        foreach ($collectionFields['fields'] as $collectionField) {
            $tableColumn = [];

            $tableColumn['width'] = $collectionField['width'] ?? 10;
            $tableColumn['label'] = $collectionField['label'] ?? $collectionField['name'];
            $tableColumn['field'] = str_replace('model.', '', $collectionField['name']);

            if ($tableColumn['field'] !== 'page_id') {
                $table[] = $tableColumn;
            }
        }

        return $table;
    }

    /**
     * Get all available collections from the setup collections folder
     */
    public static function getAllCollections(): array
    {
        $collectionsPath = base_path(self::COLLECTIONS_PATH);

        if (! is_dir($collectionsPath)) {
            return [];
        }

        $collectionFiles = glob($collectionsPath . '/*.yml');
        $collections = [];

        foreach ($collectionFiles as $file) {
            $collectionKey = basename($file, '.yml');

            try {
                $content = file_get_contents($file);
                $collectionData = Yaml::parse($content ?: '');

                if ($collectionData) {
                    $collections[] = [
                        'key' => $collectionKey,
                        'title' => $collectionData['title'] ?? ucfirst($collectionKey),
                        'titleList' => $collectionData['titleList'] ?? ucfirst($collectionKey),
                        'buttonList' => $collectionData['buttonList'] ?? 'Neuer Eintrag',
                    ];
                }
            } catch (Exception) {
                continue;
            }
        }

        return $collections;
    }
}
