<?php

use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Noerd\Helpers\CurrencyHelper;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\ThemeHelper;
use Noerd\Models\NoerdSettings;
use Noerd\Services\ThemeRegistry;
use Noerd\Traits\NoerdDetail;

new class () extends Component {
    use NoerdDetail;

    public $detailModel = NoerdSettings::class;

    #[Locked]
    public $clientId = null;

    public array $settingsData = [];

    public function mount(): void
    {
        // Defense in depth: these tenant-wide settings can be reached outside the
        // setup route (modal stack / generic component page). Enforce admin access
        // here too, independent of the dynamic-mount guard.
        abort_unless(NoerdAuth::user()?->isAdmin(), 403);

        $this->clientId = auth()->user()->selected_tenant_id;
        $settings = NoerdSettings::where('tenant_id', $this->clientId)->first();

        $this->settingsData = [
            'currency' => $settings->currency ?? 'EUR',
            'detail_theme' => $settings->detail_theme ?? config('noerd.theme.default', 'default'),
            'detail_theme_enforced' => (bool) ($settings->detail_theme_enforced ?? false),
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
        $rules = [
            'settingsData.detail_theme' => ['required', 'in:' . implode(',', array_keys($this->themeOptions()))],
            'settingsData.detail_theme_enforced' => ['boolean'],
        ];

        if (config('noerd.features.currency', true)) {
            $rules['settingsData.currency'] = ['required', 'in:EUR,USD,GBP,CHF,CZK,DKK'];
        }

        $this->validate($rules);

        $payload = [
            'detail_theme' => $this->settingsData['detail_theme'],
            'detail_theme_enforced' => (bool) ($this->settingsData['detail_theme_enforced'] ?? false),
        ];

        if (config('noerd.features.currency', true)) {
            $payload['currency'] = $this->settingsData['currency'];
        }

        NoerdSettings::updateOrCreate(
            ['tenant_id' => $this->clientId],
            $payload,
        );

        CurrencyHelper::clearCache();
        ThemeHelper::clearCache();

        $this->showSuccessIndicator = true;
    }
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>
            {{ __('System Settings') }}
        </x-noerd::modal-title>
    </x-slot:header>

    <div class="my-12 flex flex-col gap-8">
        @if(config('noerd.features.currency', true))
            <x-noerd::box>
                <div class="mt-4">
                    <x-noerd::input-label>
                        {{ __('Currency') }}
                    </x-noerd::input-label>
                    <x-noerd::select-input wire:model.live="settingsData.currency">
                        <option value="EUR">EUR - Euro (1.234,56 €)</option>
                        <option value="USD">USD - US Dollar ($1,234.56)</option>
                        <option value="GBP">GBP - British Pound (£1,234.56)</option>
                        <option value="CHF">CHF - {{ __('Swiss Franc') }} (CHF 1'234.56)</option>
                        <option value="CZK">CZK - {{ __('Czech Koruna') }} (1.234,56 Kč)</option>
                        <option value="DKK">DKK - {{ __('Danish Krone') }} (1.234,56 kr)</option>
                    </x-noerd::select-input>
                    <p class="text-sm text-gray-500 mt-1">{{ __('Select the currency for your company') }}</p>
                </div>
            </x-noerd::box>
        @endif

        <x-noerd::box>
            <div class="mt-4">
                <x-noerd::input-label>
                    {{ __('Theme') }}
                </x-noerd::input-label>
                <x-noerd::select-input wire:model.live="settingsData.detail_theme">
                    @foreach($this->themeOptions() as $themeName => $themeLabel)
                        <option value="{{ $themeName }}">{{ __($themeLabel) }}</option>
                    @endforeach
                </x-noerd::select-input>
                <p class="text-sm text-gray-500 mt-1">
                    {{ __('Default theme for all detail forms. A detail configuration that defines its own theme keeps it.') }}
                </p>
            </div>

            <div class="mt-6">
                <x-noerd::checkbox wire:model.live="settingsData.detail_theme_enforced">
                    {{ __('Enforce in Setup') }}
                </x-noerd::checkbox>
                <p class="text-sm text-gray-500 mt-1">
                    {{ __('Apply the selected theme everywhere, even where a detail configuration defines a different one.') }}
                </p>
            </div>
        </x-noerd::box>
    </div>

    <x-slot:footer>
        <x-noerd::delete-save-bar class="relative" :show-delete="false"></x-noerd::delete-save-bar>
    </x-slot:footer>
</x-noerd::page>
