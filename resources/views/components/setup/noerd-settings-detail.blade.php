<?php

use Livewire\Attributes\Locked;
use Livewire\Component;
use Noerd\Helpers\CurrencyHelper;
use Noerd\Models\NoerdSettings;
use Noerd\Traits\NoerdDetail;

new class () extends Component {
    use NoerdDetail;

    public const DETAIL_CLASS = NoerdSettings::class;

    #[Locked]
    public $clientId = null;

    public array $settingsData = [];

    public function mount(): void
    {
        $this->clientId = auth()->user()->selected_tenant_id;
        $settings = NoerdSettings::where('tenant_id', $this->clientId)->first();

        if ($settings) {
            $this->settingsData = $settings->toArray();
        } else {
            $this->settingsData = [
                'currency' => 'EUR',
            ];
        }
    }

    public function store(): void
    {
        $this->validate([
            'settingsData.currency' => ['required', 'in:EUR,USD,GBP'],
        ]);

        NoerdSettings::updateOrCreate(
            ['tenant_id' => $this->clientId],
            ['currency' => $this->settingsData['currency']],
        );

        CurrencyHelper::clearCache();

        $this->showSuccessIndicator = true;
    }
}; ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>
            {{ __('noerd_label_currency_settings_title') }}
        </x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::box>
        <div class="mt-4">
            <x-noerd::input-label>
                {{ __('noerd_label_currency') }}
            </x-noerd::input-label>
            <x-noerd::select-input wire:model.live="settingsData.currency">
                <option value="EUR">EUR - Euro (1.234,56 €)</option>
                <option value="USD">USD - US Dollar ($1,234.56)</option>
                <option value="GBP">GBP - British Pound (£1,234.56)</option>
            </x-noerd::select-input>
            <p class="text-sm text-gray-500 mt-1">{{ __('noerd_label_currency_desc') }}</p>
        </div>
    </x-noerd::box>

    <x-slot:footer>
        <x-noerd::delete-save-bar class="relative" :show-delete="false"></x-noerd::delete-save-bar>
    </x-slot:footer>
</x-noerd::page>
