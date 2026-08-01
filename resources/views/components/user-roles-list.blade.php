<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Noerd\Models\UserRole;
use Noerd\Traits\NoerdList;

new class extends Component {
    use NoerdList;

    public const DETAIL_COMPONENT = 'noerd::user-roles-list';

    public $listModel = UserRole::class;
    public ?string $detailRoute = 'user-role.detail';

    public function mount(): void
    {
        $this->mountList();
        $this->setDefaultSort('name', true);
    }

    public function listData(): array
    {
        $rows = $this->listQuery($this->listModel)
            ->where('tenant_id', auth()->user()->selected_tenant_id)
            ->paginate($this->perPage);

        return $this->buildList($rows);
    }

    public function rendering(): void
    {
        if (! Auth::user()->isAdmin()) {
            abort(401);
        }

        if ((int) request()->userRoleId) {
            $this->listAction(request()->userRoleId);
        }

        if (request()->create) {
            $this->listAction();
        }
    }
}; ?>

<x-noerd::page>

    <x-noerd::list />

</x-noerd::page>
