<?php

declare(strict_types=1);

namespace Noerd\Support;

use Noerd\Helpers\SetupCollectionHelper;
use Noerd\Models\SetupLanguage;

/**
 * @internal
 */
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
     * Convert data to translatable field format: a map of the tenant's active
     * language codes. A scalar is copied to every language as a starting
     * point; a structured value (a list, a repeater row) cannot be stringified
     * and starts with empty slots instead of the literal "Array".
     *
     * @return array<string, mixed>
     */
    private static function convertToTranslatableField(mixed $value): array
    {
        if (is_array($value) && self::isLanguageMap($value)) {
            return $value;
        }

        $codes = SetupLanguage::activeCodes() ?: ['en'];
        $stringValue = is_scalar($value) ? (string) $value : '';

        return array_fill_keys($codes, $stringValue);
    }

    /**
     * Convert data from translatable field format to a simple field. Only a
     * language map collapses — the tenant's default language first, then the
     * first non-empty translation; a list or a structured value stays as it
     * is, otherwise a type switch would wipe the entry's data.
     */
    private static function convertFromTranslatableField(mixed $value): mixed
    {
        if (! is_array($value) || ! self::isLanguageMap($value)) {
            return $value;
        }

        $default = $value[SetupLanguage::defaultCode()] ?? null;
        if ($default !== null && $default !== '') {
            return $default;
        }

        foreach ($value as $translation) {
            if ($translation !== null && $translation !== '') {
                return $translation;
            }
        }

        return '';
    }

    /**
     * Whether an array is a translatable value: non-empty, every key a language
     * code (`de`, `en-GB`), every value a scalar or null.
     *
     * @param  array<int|string, mixed>  $value
     */
    private static function isLanguageMap(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        foreach ($value as $key => $translation) {
            if (! is_string($key) || ! preg_match('/^[a-z]{2,3}(-[A-Za-z0-9]{2,8})?$/', $key)) {
                return false;
            }

            if ($translation !== null && ! is_scalar($translation)) {
                return false;
            }
        }

        return true;
    }
}
