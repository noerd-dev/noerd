<?php

namespace Noerd\Noerd\Traits;

use Noerd\Noerd\Models\SetupLanguage;

trait SetupLanguageFilterTrait
{
    protected function hasMultipleLanguages(): bool
    {
        return SetupLanguage::where('is_active', true)->count() > 1;
    }

    protected function getLanguageFilter(): array
    {
        $filter['label'] = __('noerd_label_language');
        $filter['column'] = 'language';
        $filter['type'] = 'Picklist';
        $filter['options'] = [];

        $languages = SetupLanguage::where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('name', 'asc')
            ->get();

        foreach ($languages as $language) {
            $filter['options'][$language->code] = $language->name;
        }

        return $filter;
    }

    protected function getDefaultLanguageCode(): string
    {
        return SetupLanguage::getDefaultCode();
    }

    protected function getActiveTenantLanguageCodes(): array
    {
        return SetupLanguage::getActiveCodes();
    }
}
