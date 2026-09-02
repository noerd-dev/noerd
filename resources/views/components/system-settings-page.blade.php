<?php

use Illuminate\Support\Str;
use Livewire\Component;
use Noerd\Helpers\CurrencyHelper;
use Noerd\Helpers\FormatHelper;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\ThemeHelper;
use Noerd\Models\NoerdSettings;
use Noerd\Services\ThemeRegistry;
use Noerd\Support\Locales;
use Noerd\Traits\NoerdSettingsPage;

new class extends Component {
    use NoerdSettingsPage;

    public array $settingsModels = [
        'detailData' => NoerdSettings::class,
    ];

    public function mount(): void
    {
        // Defense in depth: these tenant-wide settings can be reached outside the
        // setup route (modal stack / generic component page). Enforce admin access
        // here too, independent of the dynamic-mount guard.
        abort_unless(NoerdAuth::user()?->isAdmin(), 403);

        $this->initSettings();

        // A tenant without a settings row sees the effective defaults.
        $this->detailData['currency'] = $this->detailData['currency'] ?? CurrencyHelper::codeForTenant();
        $this->detailData['locale'] = Locales::isSupported($this->detailData['locale'] ?? null)
            ? $this->detailData['locale']
            : FormatHelper::tenantLocale();
        $this->detailData['detail_theme'] = ($this->detailData['detail_theme'] ?? null) ?: config('noerd.theme.default', 'default');
        $this->detailData['detail_theme_enforced'] = (bool) ($this->detailData['detail_theme_enforced'] ?? false);

        if (! config('noerd.features.currency', true)) {
            $this->pageLayout['fields'] = array_values(array_filter(
                $this->pageLayout['fields'] ?? [],
                fn(array $field): bool => ($field['name'] ?? '') !== 'detailData.currency',
            ));
        }
    }

    /**
     * @return array<string, string> currency code => display label
     */
    public function currencyOptions(): array
    {
        return CurrencyHelper::options();
    }

    /**
     * The tenant's DOCUMENT locale: number and date format of PDFs, receipts
     * and customer e-mails, independent of the user who generates them.
     *
     * @return array<string, string> locale => display label
     */
    public function localeOptions(): array
    {
        return Locales::options();
    }

    /**
     * @return array<string, string> theme name => display label
     */
    public function themeOptions(): array
    {
        $options = [];
        foreach (app(ThemeRegistry::class)->all() as $themeName => $definition) {
            // Internal themes (e.g. the settings-page theme) are never a valid
            // tenant-wide form theme.
            if ($definition->hidden) {
                continue;
            }

            $options[$themeName] = $definition->label ?? Str::headline($themeName);
        }

        return $options;
    }

    public function store(): void
    {
        if (! $this->canWriteObject()) {
            return;
        }

        $rules = [
            'detailData.locale' => ['required', 'in:' . implode(',', Locales::SUPPORTED)],
            'detailData.detail_theme' => ['required', 'in:' . implode(',', array_keys($this->themeOptions()))],
            'detailData.detail_theme_enforced' => ['boolean'],
        ];

        if (config('noerd.features.currency', true)) {
            $rules['detailData.currency'] = ['required', 'in:' . implode(',', array_keys(CurrencyHelper::CURRENCIES))];
        } else {
            // The field is not part of the form — never write a currency then.
            unset($this->detailData['currency']);
        }

        $this->validate($rules);
        $this->detailData['detail_theme_enforced'] = (bool) ($this->detailData['detail_theme_enforced'] ?? false);

        $this->validateFromLayout();
        $this->persistSettings();

        CurrencyHelper::clearCache();
        FormatHelper::clearCache();
        ThemeHelper::clearCache();

        $this->showSuccessIndicator = true;
    }
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ __('System Settings') }}</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" :modelId="$modelId" />

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="false" />
    </x-slot:footer>
</x-noerd::page>
