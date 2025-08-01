<?php

use Noerd\Noerd\Models\UserRole;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Noerd\Noerd\Helpers\StaticConfigHelper;
use Noerd\Noerd\Traits\NoerdModelTrait;

new class extends Component {

    use NoerdModelTrait;

    public const COMPONENT = 'user-role-component';
    public const LIST_COMPONENT = 'user-roles-table';
    public const ID = 'userRoleId';

    #[Url(keep: false, except: '')]
    public $userRoleId = null;

    public array $userRole;

    public function mount(UserRole $userRole): void
    {
        if ($this->modelId) {
            $userRole = UserRole::find($this->modelId);
        }

        $this->pageLayout = StaticConfigHelper::getComponentFields('userRole');

        $this->userRole = $userRole->toArray();
        $this->userRoleId = $userRole->id;
    }

    public function store(): void
    {
        $this->validate([
            'userRole.key' => ['required', 'string', 'max:255'],
            'userRole.name' => ['required', 'string', 'max:255'],
        ]);

        $this->userRole['tenant_id'] = auth()->user()->selected_tenant_id;
        $userRole = UserRole::updateOrCreate(['id' => $this->userRoleId], $this->userRole);

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

    @include('noerd::components.detail.block', $pageLayout)

    <x-slot:footer>
        <x-noerd::delete-save-bar/>
    </x-slot:footer>
</x-noerd::page>
