<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Noerd\Models\SetupLanguage;

new class extends Component {
    public string $locale = '';

    public array $languages = [];

    public function mount(): void
    {
        $this->locale = Auth::user()->locale ?? 'en';
        $this->languages = SetupLanguage::getActive()
            ->map(fn (SetupLanguage $language) => [
                'value' => $language->code,
                'label' => $language->name,
            ])
            ->toArray();
    }

    public function updateLanguage(): void
    {
        $activeCodes = implode(',', SetupLanguage::getActiveCodes());

        $validated = $this->validate([
            'locale' => ['required', 'string', "in:{$activeCodes}"],
        ]);

        Auth::user()->setting->update($validated);

        $this->dispatch('language-updated');
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('noerd_language') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('noerd_language_description') }}
        </p>
    </header>

    <form wire:submit="updateLanguage" class="mt-6 space-y-6">
        <x-noerd::forms.input-select
            name="locale"
            label="{{ __('noerd_label_language') }}"
            :options="$languages"
        />

        <div class="flex items-center gap-4">
            <x-noerd::button>{{ __('Save') }}</x-noerd::button>

            <x-noerd::action-message class="me-3" on="language-updated">
                {{ __('Saved.') }}
            </x-noerd::action-message>
        </div>
    </form>
</section>
