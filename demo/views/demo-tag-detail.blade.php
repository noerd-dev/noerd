<?php

use App\Models\DemoTag;
use Livewire\Component;
use Noerd\Traits\NoerdDetail;

new class extends Component {
    use NoerdDetail;

    public $detailModel = DemoTag::class;

    public ?string $detailPrimary = 'demoTagId';

    public function store(): void
    {
        $this->validateFromLayout();

        $demoTag = DemoTag::updateOrCreate(
            ['id' => $this->modelId],
            array_merge($this->detailData, ['tenant_id' => auth()->user()->selected_tenant_id]),
        );

        $this->storeProcess($demoTag);
    }

};
?>

<x-noerd::page>
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
