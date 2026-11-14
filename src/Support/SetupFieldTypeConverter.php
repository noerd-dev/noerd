<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Support\Str;
use Noerd\Helpers\SetupCollectionHelper;
use Noerd\Helpers\TenantHelper;
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
        $languageCodes = self::languageCodes();

        foreach ($collectionFields['fields'] as $field) {
            $fieldName = str_replace('detailData.', '', $field['name']);
            $fieldType = $field['type'] ?? 'text';

            if (! array_key_exists($fieldName, $currentData)) {
                continue;
            }

            $currentValue = $currentData[$fieldName];

            // Convert based on target field type
            if (in_array($fieldType, ['translatableText', 'translatableRichText', 'translatableTextarea'], true)) {
                $convertedData[$fieldName] = self::convertToTranslatableField($currentValue, $languageCodes);
            } else {
                $convertedData[$fieldName] = self::convertFromTranslatableField($currentValue, $languageCodes);
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
     * @param  list<string>|null  $languageCodes  see languageCodes(); resolved when null
     * @return array<string, mixed>
     */
    private static function convertToTranslatableField(mixed $value, ?array $languageCodes = null): array
    {
        if (is_array($value) && self::isLanguageMap($value, $languageCodes ?? self::languageCodes())) {
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
     *
     * @param  list<string>|null  $languageCodes  see languageCodes(); resolved when null
     */
    private static function convertFromTranslatableField(mixed $value, ?array $languageCodes = null): mixed
    {
        if (! is_array($value) || ! self::isLanguageMap($value, $languageCodes ?? self::languageCodes())) {
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
     * Whether an array is a translatable value: non-empty, every key one of the
     * given language codes (compared case-insensitively), every value a scalar
     * or null. A plain map such as `['min' => 1, 'max' => 5]` is no translation
     * although its keys LOOK like language codes.
     *
     * @param  array<int|string, mixed>  $value
     * @param  list<string>  $languageCodes
     */
    private static function isLanguageMap(array $value, array $languageCodes): bool
    {
        if ($value === []) {
            return false;
        }

        $known = array_flip($languageCodes);

        foreach ($value as $key => $translation) {
            if (! is_string($key) || ! isset($known[mb_strtolower($key)])) {
                return false;
            }

            if ($translation !== null && ! is_scalar($translation)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The language codes a translatable value may be keyed by, lowercased: the
     * codes of the tenant's setup languages (active or not — a deactivated
     * language keeps its translations). Without a known tenant, or for a tenant
     * without any setup language, the codes of the supported locales
     * (`de`, `de-de`, …) and the default `en`.
     *
     * @return list<string>
     */
    private static function languageCodes(): array
    {
        $codes = TenantHelper::currentTenantId() !== null
            ? SetupLanguage::query()->pluck('code')->all()
            : [];

        if ($codes === []) {
            $codes = ['en'];

            foreach (Locales::SUPPORTED as $locale) {
                $codes[] = $locale;
                $codes[] = Str::before($locale, '-');
            }
        }

        return array_values(array_unique(array_map(
            fn(mixed $code): string => mb_strtolower((string) $code),
            $codes,
        )));
    }
}
