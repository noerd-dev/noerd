<?php

use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Noerd\Models\Tenant;
use Noerd\Traits\NoerdDetail;

new class extends Component {
    use NoerdDetail;
    use WithFileUploads;

    #[Url(as: 'tenantId', keep: false, except: '')]
    public $modelId = null;

    public const DETAIL_CLASS = Tenant::class;

    public $logo;

    public function mount(): void
    {
        $this->initDetail();

        $tenant = Tenant::find(auth()->user()->selected_tenant_id);

        $this->detailData = $tenant->toArray();
    }

    public function store(): void
    {
        $this->validate([
            'detailData.name' => ['required', 'string', 'max:255', 'min:3'],
        ]);

        $tenant = Tenant::find(auth()->user()->selected_tenant_id);
        $tenant->name = $this->detailData['name'];
        $tenant->logo = $this->detailData['logo'];
        $tenant->save();

        $this->showSuccessIndicator = true;
    }

    public function delete(): void
    {
    }

    public function updatedLogo()
    {
        $this->storeFile();
    }

    public function storeFile()
    {
        $link = $this->logo->storePublicly(path: 'uploads', options: 'public');
        $this->detailData['logo'] = '/storage/' . $link;
    }

    public function deleteImage()
    {
        $this->detailData['logo'] = null;
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>
            Mandanten
        </x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout">
        <x-slot:tab1>
            <x-noerd::box>
                <div class="max-w-xl">
                    <livewire:setup.create-new-tenant/>
                </div>
            </x-noerd::box>
        </x-slot:tab1>
    </x-noerd::tab-content>

    <x-slot:footer>
        <x-noerd::delete-save-bar/>
    </x-slot:footer>
</x-noerd::page>
