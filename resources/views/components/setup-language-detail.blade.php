<?php

use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\SetupLanguage;
use Noerd\Traits\NoerdDetail;

new class extends Component {
    use NoerdDetail {
        store as protected storeLanguage;
    }

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
    #[Computed]
    public function canDeleteLanguage(): bool
    {
        if (! $this->modelId || ($this->detailData['is_default'] ?? false)) {
            return false;
        }

        return SetupLanguage::count() > 1;
    }

    /**
     * The code is rendered into markup and script of every translatable field
     * and becomes the application locale — it is a language tag, nothing else.
     * It is unique per tenant: a second language with the same code is a
     * validation error, not a failing insert.
     */
    public function store(): void
    {
        if (is_string($this->detailData['code'] ?? null)) {
            $this->detailData['code'] = mb_trim($this->detailData['code']);
        }

        $this->validate(
            ['detailData.code' => [
                'required',
                'string',
                'regex:' . SetupLanguage::CODE_PATTERN,
                Rule::unique('setup_languages', 'code')
                    ->where('tenant_id', TenantHelper::currentTenantId())
                    ->ignore($this->modelId),
            ]],
            [
                'detailData.code.regex' => __('The code must be a language code such as "de" or "en-GB".'),
                'detailData.code.unique' => __('A language with this code already exists.'),
            ],
        );

        $this->storeLanguage();
    }

    public function delete(): void
    {
        if (! $this->canDeleteLanguage) {
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
        <x-noerd::delete-save-bar :showDelete="$this->canDeleteLanguage"/>
    </x-slot:footer>
</x-noerd::page>
