<?php

use Livewire\Volt\Component;
use Noerd\Noerd\Helpers\StaticConfigHelper;
use Noerd\Noerd\Models\Language;
use Noerd\Noerd\Traits\Noerd;

new class extends Component {

    use Noerd;

    public const COMPONENT = 'language-detail';
    public const LIST_COMPONENT = 'languages-list';
    public const ID = 'languageId';
    #[\Livewire\Attributes\Url(keep: false, except: '')]
    public ?string $languageId = null;

    public array $model;
    public Language $language;

    public function mount(Language $model): void
    {
        if ($this->modelId) {
            $model = Language::find($this->modelId);
        }

        $this->mountModalProcess(self::COMPONENT, $model);
        $this->language = $model;
    }

    public function store(): void
    {
        $this->validate([
            'model.code' => ['required', 'string', 'max:10'],
            'model.name' => ['required', 'string', 'max:100'],
            'model.is_active' => ['boolean'],
            'model.is_default' => ['boolean'],
            'model.sort_order' => ['nullable', 'integer'],
        ]);

        $data = $this->model;
        $data['tenant_id'] = auth()->user()->selected_tenant_id;
        $language = Language::updateOrCreate(['id' => $this->modelId], $data);

        if (!empty($data['is_default'])) {
            Language::where('tenant_id', auth()->user()->selected_tenant_id)
                ->where('id', '!=', $language->id)
                ->update(['is_default' => false]);
        }

        $this->storeProcess($language);
    }

    public function delete(): void
    {
        $language = Language::find($this->modelId);
        if ($language) {
            $language->delete();
        }
        $this->closeModalProcess(self::LIST_COMPONENT);
    }

} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>Sprache</x-noerd::modal-title>
    </x-slot:header>

    @php($pageLayout = StaticConfigHelper::getComponentFields('language-detail'))
    @include('noerd::components.detail.block', $pageLayout)

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="$languageId"/>
    </x-slot:footer>
</x-noerd::page>

