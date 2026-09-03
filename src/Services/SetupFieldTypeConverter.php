<?php

declare(strict_types=1);

namespace Noerd\Services;

use Noerd\Helpers\SetupCollectionHelper;
use Noerd\Models\SetupLanguage;

final class SetupFieldTypeConverter
{
    /**
     * Convert field data based on collection field type changes
     *
     * @param  array  $currentData  Current entry data
     * @param  string  $collectionKey  Collection key to get field definitions
     * @return array Converted data
     */
    public static function convertCollectionData(array $currentData, string $collectionKey): array
    {
        $collectionFields = SetupCollectionHelper::getCollectionFields($collectionKey);

        if (! $collectionFields || ! isset($collectionFields['fields'])) {
            return $currentData;
        }

        $convertedData = $currentData;

        foreach ($collectionFields['fields'] as $field) {
            $fieldName = str_replace('detailData.', '', $field['name']);
            $fieldType = $field['type'] ?? 'text';

            if (! array_key_exists($fieldName, $currentData)) {
                continue;
            }

            $currentValue = $currentData[$fieldName];

            // Convert based on target field type
            if (in_array($fieldType, ['translatableText', 'translatableRichText', 'translatableTextarea'], true)) {
                $convertedData[$fieldName] = self::convertToTranslatableField($currentValue);
            } else {
                $convertedData[$fieldName] = self::convertFromTranslatableField($currentValue);
            }
        }

        return $convertedData;
    }

    /**
     * Convert data to translatable field format
     */
    private static function convertToTranslatableField(mixed $value): array
    {
        $codes = SetupLanguage::activeCodes() ?: ['en'];

        // If already in translatable format (keyed by an active language), return as-is
        if (is_array($value) && array_intersect($codes, array_keys($value)) !== []) {
            return $value;
        }

        $stringValue = is_string($value) ? $value : (string) $value;

        return array_fill_keys($codes, $stringValue);
    }

    /**
     * Convert data from translatable field format to simple field
     */
    private static function convertFromTranslatableField(mixed $value): mixed
    {
        // If it's a translatable array, extract the tenant's default language
        if (is_array($value)) {
            return $value[SetupLanguage::defaultCode()] ?? (reset($value) ?: '');
        }

        return $value;
    }
}
