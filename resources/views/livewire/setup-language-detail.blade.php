<?php

use Livewire\Attributes\Url;
use Livewire\Component;
use Noerd\Models\SetupLanguage;
use Noerd\Traits\NoerdDetail;

new class extends Component
{
    use NoerdDetail;

    #[Url(as: 'setupLanguageId', keep: false, except: '')]
    public $modelId = null;

    public const DETAIL_CLASS = SetupLanguage::class;

    public function mount(mixed $model = null): void
    {
        $this->initDetail($model);

        $language = new SetupLanguage;
        if ($this->modelId) {
            $language = SetupLanguage::find($this->modelId) ?? new SetupLanguage;
        }

        $this->detailData = $language->toArray();

        // Set defaults for new languages
        if (! $language->exists) {
            $this->detailData['is_active'] = true;
            $this->detailData['is_default'] = false;
            $this->detailData['sort_order'] = SetupLanguage::max('sort_order') + 1;
        }
    }

    public function store(): void
    {
        $this->validateFromLayout();

        $language = SetupLanguage::updateOrCreate(
            ['id' => $this->modelId],
            $this->detailData
        );

        $this->showSuccessIndicator = true;

        if ($language->wasRecentlyCreated) {
            $this->modelId = $language->id;
        }
    }

    public function delete(): void
    {
        $language = SetupLanguage::find($this->modelId);

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
        $this->closeModalProcess($this->getListComponent());
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>{{ __('noerd_label_language') }}</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout">
        <x-slot:tab1>
            @if($modelId && $detailData['is_default'] ?? false)
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
        <x-noerd::delete-save-bar :showDelete="isset($modelId) && !($detailData['is_default'] ?? false)"/>
    </x-slot:footer>
</x-noerd::page>
