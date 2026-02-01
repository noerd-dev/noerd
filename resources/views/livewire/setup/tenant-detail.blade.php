<?php

use Noerd\Models\Tenant;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Noerd\Traits\Noerd;

new class extends Component {

    use Noerd;
    use WithFileUploads;

    public const DETAIL_COMPONENT = 'tenant-detail';
    public const LIST_COMPONENT = 'tenants-list';
    public const ID = 'tenantId';

    #[Url(keep: false, except: '')]
    public $tenantId = null;

    public array $tenantData = [];
    public $logo;

    public function mount(Tenant $tenant): void
    {
        $tenant = Tenant::find(auth()->user()->selected_tenant_id);

        $this->mountModalProcess(self::DETAIL_COMPONENT, $tenant);
        $this->tenantData = $tenant->toArray();
    }

    public function store(): void
    {
        $this->validate([
            'tenantData.name' => ['required', 'string', 'max:255', 'min:3'],
        ]);

        $tenant = Tenant::find(auth()->user()->selected_tenant_id);
        $tenant->name = $this->tenantData['name'];
        $tenant->logo = $this->tenantData['logo'];
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
        $this->tenantData['logo'] = '/storage/' . $link;
    }

    public function deleteImage()
    {
        $this->tenantData['logo'] = null;
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
