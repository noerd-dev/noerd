<?php

use Livewire\Attributes\Url;
use Livewire\Component;
use Noerd\Models\UserRole;
use Noerd\Traits\NoerdDetail;

new class extends Component {
    use NoerdDetail;

    #[Url(as: 'userRoleId', keep: false, except: '')]
    public $modelId = null;

    public const DETAIL_CLASS = UserRole::class;

    public function mount(): void
    {
        $this->initDetail();

        $userRole = new UserRole;
        if ($this->modelId) {
            $userRole = UserRole::find($this->modelId);
        }

        $this->detailData = $userRole->toArray();
    }

    public function store(): void
    {
        $this->validateFromLayout();

        $this->detailData['tenant_id'] = auth()->user()->selected_tenant_id;
        $userRole = UserRole::updateOrCreate(['id' => $this->modelId], $this->detailData);

        $this->showSuccessIndicator = true;

        if ($userRole->wasRecentlyCreated) {
            $this->modelId = $userRole['id'];
        }
    }

    public function delete(): void
    {
        $userRole = UserRole::find($this->modelId);
        $userRole->delete();
        $this->closeModalProcess($this->getListComponent());
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>Benutzerrolle</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" />

    <x-slot:footer>
        <x-noerd::delete-save-bar/>
    </x-slot:footer>
</x-noerd::page>
