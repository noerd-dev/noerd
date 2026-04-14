<?php

use App\Models\DemoTag;
use Livewire\Attributes\Url;
use Livewire\Component;
use Noerd\Traits\NoerdDetail;

new class extends Component {
    use NoerdDetail;

    public const DETAIL_CLASS = DemoTag::class;

    #[Url(as: 'demoTagId', keep: false, except: '')]
    public $modelId = null;

    public function mount(): void
    {
        $this->initDetail();

        if ($this->modelId) {
            $demoTag = DemoTag::find($this->modelId);
            if ($demoTag) {
                $this->detailData = $demoTag->toArray();
            }
        }
    }

    public function store(): void
    {
        $this->validateFromLayout();

        $demoTag = DemoTag::updateOrCreate(
            ['id' => $this->modelId],
            array_merge($this->detailData, ['tenant_id' => auth()->user()->selected_tenant_id]),
        );

        $this->storeProcess($demoTag);
    }

    public function delete(): void
    {
        DemoTag::find($this->modelId)?->delete();
        $this->closeModalProcess($this->getListComponent());
    }
};
?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Demo Tag') }}</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" :modelId="$modelId">
        <x-slot:tab1>
        </x-slot:tab1>
    </x-noerd::tab-content>

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="isset($modelId)"/>
    </x-slot:footer>
</x-noerd::page>
