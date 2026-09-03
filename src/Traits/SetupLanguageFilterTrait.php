<?php

declare(strict_types=1);

namespace Noerd\Traits;

use Noerd\Models\SetupLanguage;

trait SetupLanguageFilterTrait
{
    protected function hasMultipleLanguages(): bool
    {
        return SetupLanguage::where('is_active', true)->count() > 1;
    }

    /**
     * @return array{label: string, column: string, type: string, options: array<string, string>}
     */
    protected function getLanguageListFilter(): array
    {
        $languages = SetupLanguage::where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('name', 'asc')
            ->get();

        return [
            'label' => __('Language'),
            'column' => 'language',
            'type' => 'Picklist',
            'options' => $languages->pluck('name', 'code')->all(),
        ];
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
