<?php

use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Noerd\Models\SetupLanguage;
use Noerd\Traits\Noerd;

new class extends Component
{
    use Noerd;

    public const COMPONENT = 'setup-language-detail';
    public const LIST_COMPONENT = 'setup-languages-list';
    public const ID = 'languageId';

    #[Url(keep: false, except: '')]
    public $languageId = null;

    public array $languageData = [];

    public function mount(SetupLanguage $language): void
    {
        if ($this->languageId) {
            $language = SetupLanguage::find($this->languageId) ?? new SetupLanguage;
        }

        $this->mountModalProcess(self::COMPONENT, $language);

        $this->languageData = $language->toArray();

        // Set defaults for new languages
        if (! $language->exists) {
            $this->languageData['is_active'] = true;
            $this->languageData['is_default'] = false;
            $this->languageData['sort_order'] = SetupLanguage::max('sort_order') + 1;
        }
    }

    public function store(): void
    {
        $this->validateFromLayout();

        $language = SetupLanguage::updateOrCreate(
            ['id' => $this->languageId],
            $this->languageData
        );

        $this->showSuccessIndicator = true;

        if ($language->wasRecentlyCreated) {
            $this->languageId = $language->id;
            $this->languageId = $language->id;
        }
    }

    public function delete(): void
    {
        $language = SetupLanguage::find($this->languageId);

        // Prevent deleting the last language
        if (SetupLanguage::count() <= 1) {
            session()->flash('error', __('noerd_cannot_delete_last_language'));

            return;
        }

        // Prevent deleting default language if it's the only one
        if ($language?->is_default && SetupLanguage::where('is_active', true)->count() <= 1) {
            session()->flash('error', __('noerd_cannot_delete_default_language'));

            return;
        }

        $language?->delete();
        $this->closeModalProcess(self::LIST_COMPONENT);
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>{{ __('noerd_label_language') }}</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout">
        <x-slot:tab1>
            @if($languageId && $languageData['is_default'] ?? false)
                <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <p class="text-sm text-blue-800">
                        <x-icon name="information-circle" class="w-5 h-5 inline-block mr-1"/>
                        {{ __('noerd_default_language_info') }}
                    </p>
                </div>
            @endif
        </x-slot:tab1>
    </x-noerd::tab-content>

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="isset($languageId) && !($languageData['is_default'] ?? false)"/>
    </x-slot:footer>
</x-noerd::page>
