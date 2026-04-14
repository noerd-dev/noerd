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

    public bool $hasUsers = false;

    public function mount(): void
    {
        $this->initDetail();

        $userRole = new UserRole;
        if ($this->modelId) {
            $userRole = UserRole::find($this->modelId);
            $this->hasUsers = $userRole->users()->exists();
        }

        $this->detailData = $userRole->toArray();
    }

    public function store(): void
    {
        $this->validateFromLayout();

        $userRole = UserRole::updateOrCreate(['id' => $this->modelId], $this->detailData);

        $this->storeProcess($userRole);
    }

    public function delete(): void
    {
        $userRole = UserRole::find($this->modelId);

        if ($userRole->users()->exists()) {
            $this->dispatch('toast', message: __('This role cannot be deleted because it still has users assigned.'), type: 'error');

            return;
        }

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
        <x-noerd::delete-save-bar :showDelete="isset($modelId) && !$hasUsers"/>
    </x-slot:footer>
</x-noerd::page>
