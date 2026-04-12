<?php

use App\Models\DemoCategory;
use Livewire\Attributes\Url;
use Livewire\Component;
use Noerd\Traits\NoerdDetail;

new class extends Component {
    use NoerdDetail;

    public const DETAIL_CLASS = DemoCategory::class;

    #[Url(as: 'demoCategoryId', keep: false, except: '')]
    public $modelId = null;

    public function mount(): void
    {
        $this->initDetail();

        if ($this->modelId) {
            $demoCategory = DemoCategory::find($this->modelId);
            if ($demoCategory) {
                $this->detailData = $demoCategory->toArray();
            }
        }
    }

    public function store(): void
    {
        $this->validateFromLayout();

        $demoCategory = DemoCategory::updateOrCreate(
            ['id' => $this->modelId],
            array_merge($this->detailData, ['tenant_id' => auth()->user()->selected_tenant_id]),
        );

        $this->storeProcess($demoCategory);
    }

    public function delete(): void
    {
        DemoCategory::find($this->modelId)?->delete();
        $this->closeModalProcess($this->getListComponent());
    }
}; ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>{{ __('ui_library_label_demo_category') }}</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" :modelId="$modelId">
        <x-slot:tab1>
        </x-slot:tab1>
    </x-noerd::tab-content>

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="isset($modelId)"/>
    </x-slot:footer>
</x-noerd::page>
