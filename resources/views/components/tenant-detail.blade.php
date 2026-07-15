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
    public const DETAIL_COMPONENT = 'noerd::tenant-detail';

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
            'detailData.email' => ['required', 'email', 'max:255'],
        ]);

        $tenant = Tenant::find(auth()->user()->selected_tenant_id);
        $tenant->name = $this->detailData['name'];
        $tenant->email = $this->detailData['email'];
        $tenant->contact_name = $this->detailData['contact_name'] ?? null;
        $tenant->address = $this->detailData['address'] ?? null;
        $tenant->zipcode = $this->detailData['zipcode'] ?? null;
        $tenant->city = $this->detailData['city'] ?? null;
        $tenant->logo = $this->detailData['logo'] ?? null;
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
        <x-noerd::modal-title>{{ __('Tenant') }}</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" />

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="false" />
    </x-slot:footer>
</x-noerd::page>
