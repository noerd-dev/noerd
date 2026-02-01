<?php

use Noerd\Models\UserRole;
use Livewire\Attributes\Url;
use Livewire\Component;
use Noerd\Traits\Noerd;

new class extends Component {

    use Noerd;

    public const COMPONENT = 'user-role-detail';
    public const LIST_COMPONENT = 'user-roles-list';
    public const ID = 'userRoleId';

    #[Url(keep: false, except: '')]
    public $userRoleId = null;

    public array $userRoleData = [];

    public function mount(UserRole $userRole): void
    {
        if ($this->userRoleId) {
            $userRole = UserRole::find($this->userRoleId);
        }

        $this->mountModalProcess(self::COMPONENT, $userRole);
        $this->userRoleData = $userRole->toArray();
    }

    public function store(): void
    {
        $this->validateFromLayout();

        $this->userRoleData['tenant_id'] = auth()->user()->selected_tenant_id;
        $userRole = UserRole::updateOrCreate(['id' => $this->userRoleId], $this->userRoleData);

        $this->showSuccessIndicator = true;

        if ($userRole->wasRecentlyCreated) {
            $this->userRoleId = $userRole['id'];
        }
    }

    public function delete(): void
    {
        $userRole = UserRole::find($this->userRoleId);
        $userRole->delete();
        $this->closeModalProcess(self::LIST_COMPONENT);
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
