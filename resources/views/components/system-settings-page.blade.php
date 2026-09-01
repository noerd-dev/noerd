<?php

use Illuminate\Support\Str;
use Livewire\Component;
use Noerd\Helpers\CurrencyHelper;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\ThemeHelper;
use Noerd\Models\NoerdSettings;
use Noerd\Services\ThemeRegistry;
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
        $this->detailData['currency'] = $this->detailData['currency'] ?? 'EUR';
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
        return [
            'EUR' => 'EUR - Euro (1.234,56 €)',
            'USD' => 'USD - US Dollar ($1,234.56)',
            'GBP' => 'GBP - British Pound (£1,234.56)',
            'CHF' => 'CHF - ' . __('Swiss Franc') . " (CHF 1'234.56)",
            'CZK' => 'CZK - ' . __('Czech Koruna') . ' (1.234,56 Kč)',
            'DKK' => 'DKK - ' . __('Danish Krone') . ' (1.234,56 kr)',
        ];
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
            'detailData.detail_theme' => ['required', 'in:' . implode(',', array_keys($this->themeOptions()))],
            'detailData.detail_theme_enforced' => ['boolean'],
        ];

        if (config('noerd.features.currency', true)) {
            $rules['detailData.currency'] = ['required', 'in:' . implode(',', array_keys($this->currencyOptions()))];
        } else {
            // The field is not part of the form — never write a currency then.
            unset($this->detailData['currency']);
        }

        $this->validate($rules);
        $this->detailData['detail_theme_enforced'] = (bool) ($this->detailData['detail_theme_enforced'] ?? false);

        $this->validateFromLayout();
        $this->persistSettings();

        CurrencyHelper::clearCache();
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
