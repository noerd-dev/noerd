<?php

use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Noerd\Noerd\Models\SetupLanguage;
use Noerd\Noerd\Traits\Noerd;

new class extends Component
{
    use Noerd;

    public const COMPONENT = 'setup-language-detail';
    public const LIST_COMPONENT = 'setup-languages-list';
    public const ID = 'languageId';

    #[Url(keep: false, except: '')]
    public $languageId = null;

    public array $language = [];

    public function mount(SetupLanguage $model): void
    {
        if ($this->modelId) {
            $model = SetupLanguage::find($this->modelId) ?? new SetupLanguage;
        }

        $this->mountModalProcess(self::COMPONENT, $model);

        $this->language = $model->toArray();

        // Set defaults for new languages
        if (! $model->exists) {
            $this->language['is_active'] = true;
            $this->language['is_default'] = false;
            $this->language['sort_order'] = SetupLanguage::max('sort_order') + 1;
        }
    }

    public function store(): void
    {
        $this->validateFromLayout();

        $language = SetupLanguage::updateOrCreate(
            ['id' => $this->languageId],
            $this->language
        );

        $this->showSuccessIndicator = true;

        if ($language->wasRecentlyCreated) {
            $this->languageId = $language->id;
            $this->modelId = $language->id;
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

    <div>
        @include('noerd::components.detail.block', $pageLayout)

        @if($languageId && $language['is_default'] ?? false)
            <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-sm text-blue-800">
                    <x-icon name="information-circle" class="w-5 h-5 inline-block mr-1"/>
                    {{ __('noerd_default_language_info') }}
                </p>
            </div>
        @endif
    </div>

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="isset($languageId) && !($language['is_default'] ?? false)"/>
    </x-slot:footer>
</x-noerd::page>
