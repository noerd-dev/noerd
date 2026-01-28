<?php

namespace Noerd\Services;

use Noerd\Helpers\SetupCollectionHelper;

class SetupFieldTypeConverter
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
            $fieldName = str_replace('model.', '', $field['name']);
            $fieldType = $field['type'] ?? 'text';

            if (! array_key_exists($fieldName, $currentData)) {
                continue;
            }

            $currentValue = $currentData[$fieldName];

            // Convert based on target field type
            if (in_array($fieldType, ['translatableText', 'translatableRichText', 'translatableTextarea'])) {
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
        // If already in translatable format, return as-is
        if (is_array($value) && (isset($value['de']) || isset($value['en']))) {
            return $value;
        }

        // Convert string to translatable format
        if (is_string($value)) {
            return [
                'de' => $value,
                'en' => $value,
            ];
        }

        // Default fallback
        $stringValue = (string) $value;

        return [
            'de' => $stringValue,
            'en' => $stringValue,
        ];
    }

    /**
     * Convert data from translatable field format to simple field
     */
    private static function convertFromTranslatableField(mixed $value): mixed
    {
        // If it's a translatable array, extract the German value as default
        if (is_array($value)) {
            return $value['de'] ?? $value['en'] ?? '';
        }

        return $value;
    }
}
