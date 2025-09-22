<?php

use Noerd\Noerd\Models\Tenant;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Noerd\Noerd\Helpers\StaticConfigHelper;
use Noerd\Noerd\Traits\Noerd;

new class extends Component {

    use Noerd;
    use WithFileUploads;

    public const COMPONENT = 'tenant-detail';
    public const LIST_COMPONENT = 'tenants-list';
    public const ID = 'tenantId';

    #[Url(keep: false, except: '')]
    public $tenantId = null;

    public array $model;
    public $logo;

    public function mount(Tenant $model): void
    {
        $model = Tenant::find(auth()->user()->selected_tenant_id);

        $this->pageLayout = StaticConfigHelper::getComponentFields('tenant');

        $this->model = $model->toArray();
        $this->tenantId = $model->id;
    }

    public function store(): void
    {
        $this->validate([
            'model.name' => ['required', 'string', 'max:255', 'min:3'],
        ]);

        $tenant = Tenant::find(auth()->user()->selected_tenant_id);
        $tenant->name = $this->model['name'];
        $tenant->logo = $this->model['logo'];
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
        $this->model['logo'] = '/storage/' . $link;
    }

    public function deleteImage()
    {
        $this->model['logo'] = null;
    }

} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>
            Mandanten
        </x-noerd::modal-title>
    </x-slot:header>

    @include('noerd::components.detail.block', $pageLayout)

    <x-noerd::box>
        <div class="max-w-xl">
            <livewire:setup.create-new-tenant/>
        </div>
    </x-noerd::box>

    <x-slot:footer>
        <x-noerd::delete-save-bar/>
    </x-slot:footer>
</x-noerd::page>
