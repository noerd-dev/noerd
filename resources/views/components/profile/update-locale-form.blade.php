<?php

use Livewire\Component;
use Noerd\Helpers\FormatHelper;
use Noerd\Helpers\NoerdAuth;
use Noerd\Support\Locales;

new class extends Component {
    public string $formatLocale = '';

    public array $locales = [];

    public function mount(): void
    {
        $user = NoerdAuth::user();

        // Unset means "follow the tenant" — the picker shows what that resolves to.
        $this->formatLocale = $user->format_locale ?? FormatHelper::locale();
        $this->locales = collect(Locales::options())
            ->map(fn (string $label, string $locale) => ['value' => $locale, 'label' => $label])
            ->values()
            ->toArray();
    }

    public function updateLocale(): void
    {
        $validated = $this->validate([
            'formatLocale' => ['required', 'string', 'in:' . implode(',', Locales::SUPPORTED)],
        ]);

        NoerdAuth::user()->setting->update(['format_locale' => $validated['formatLocale']]);
        FormatHelper::clearCache();

        $this->dispatch('locale-updated');
    }
}; ?>

<section>
    <header>
        <div class="text-lg font-medium text-gray-900">
            {{ __('Locale') }}
        </div>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Number, date and currency format for lists and forms. The interface language is a separate setting.') }}
        </p>
    </header>

    <form wire:submit="updateLocale" class="mt-6 space-y-6">
        <x-noerd::forms.input-select
            name="formatLocale"
            label="{{ __('Locale') }}"
            :options="$locales"
        />

        <div class="flex items-center gap-4">
            <x-noerd::button>{{ __('Save') }}</x-noerd::button>

            <x-noerd::action-message class="me-3" on="locale-updated">
                {{ __('Saved.') }}
            </x-noerd::action-message>
        </div>
    </form>
</section>
