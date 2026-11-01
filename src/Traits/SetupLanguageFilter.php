<?php

declare(strict_types=1);

namespace Noerd\Traits;

use Noerd\Models\SetupLanguage;
use Noerd\Support\ListFilter;

/**
 * Language helpers for lists whose rows carry a `language` column, backed by
 * the setup languages.
 */
trait SetupLanguageFilter
{
    protected function hasMultipleLanguages(): bool
    {
        return SetupLanguage::where('is_active', true)->count() > 1;
    }

    /**
     * The language dropdown: one option per active language, the default
     * language first. Applied to a `language` column by the list trait; a list
     * whose rows keep the language elsewhere reads `$this->listFilters['language']`
     * itself.
     */
    protected function getLanguageListFilter(): ListFilter
    {
        return ListFilter::options(__('Language'), 'language', fn(): array => SetupLanguage::where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('name', 'asc')
            ->pluck('name', 'code')
            ->all());
    }

    protected function getDefaultLanguageCode(): string
    {
        return SetupLanguage::defaultCode();
    }

    /**
     * @return array<int, string>
     */
    protected function getActiveTenantLanguageCodes(): array
    {
        return SetupLanguage::activeCodes();
    }
}
