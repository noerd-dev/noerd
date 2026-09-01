<?php

use Livewire\Component;
use Noerd\Models\SetupLanguage;
use Noerd\Traits\NoerdDetail;

new class extends Component {
    use NoerdDetail;

    public ?string $detailPrimary = 'setupLanguageId';

    public $detailModel = SetupLanguage::class;

    public function mount(): void
    {
        $this->initDetail();

        // The next free position — computed, so not a YAML default.
        if (! $this->modelId) {
            $this->detailData['sort_order'] = (int) SetupLanguage::max('sort_order') + 1;
        }
    }

    /**
     * The last language and the only active default language stay — the
     * delete button is hidden for them and delete() refuses.
     */
    public function canDeleteLanguage(): bool
    {
        if (! $this->modelId || ($this->detailData['is_default'] ?? false)) {
            return false;
        }

        return SetupLanguage::count() > 1;
    }

    public function delete(): void
    {
        if (! $this->canDeleteLanguage()) {
            return;
        }

        SetupLanguage::find($this->modelId)?->delete();
        $this->closeModalProcess($this->getListComponent());
    }
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Language') }}</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" :modelId="$modelId">
        <x-slot:tab1>
            @if($modelId && ($detailData['is_default'] ?? false))
                <x-noerd::info-box>
                    {{ __('This language is set as the default language and cannot be deleted.') }}
                </x-noerd::info-box>
            @endif
        </x-slot:tab1>
    </x-noerd::tab-content>

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="$this->canDeleteLanguage()"/>
    </x-slot:footer>
</x-noerd::page>
