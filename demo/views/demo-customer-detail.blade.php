<?php

use App\Models\DemoCategory;
use App\Models\DemoCustomer;
use Livewire\Attributes\Url;
use Livewire\Component;
use Noerd\Traits\NoerdDetail;

new class extends Component {
    use NoerdDetail;

    public $detailModel = DemoCustomer::class;

    #[Url(as: 'demoCustomerId', keep: false, except: '')]
    public $modelId = null;

    public function categoryOptions(): array
    {
        return ['' => '-'] + DemoCategory::orderBy('name')->pluck('name', 'id')->toArray();
    }

    public function store(): void
    {
        $this->validateFromLayout();

        $this->detailData['demo_category_id'] = ($this->detailData['demo_category_id'] ?? null) ?: null;

        $demoCustomer = DemoCustomer::updateOrCreate(
            ['id' => $this->modelId],
            array_merge($this->detailData, ['tenant_id' => auth()->user()->selected_tenant_id]),
        );

        $this->showSuccessIndicator = true;

        if (! $this->modelId) {
            $this->modelId = $demoCustomer->id;
        }
    }

};
?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>Demo Customer</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" :modelId="$modelId">
        <x-slot:tab1>
        </x-slot:tab1>
    </x-noerd::tab-content>

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="isset($modelId)"/>
    </x-slot:footer>
</x-noerd::page>
