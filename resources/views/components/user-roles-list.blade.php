<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Noerd\Facades\Noerd;
use Noerd\Models\UserRole;
use Noerd\Traits\NoerdList;

new class extends Component {
    use NoerdList;

    public const DETAIL_COMPONENT = 'noerd::user-roles-list';

    public function mount(): void
    {
        $this->mountList();
        $this->setDefaultSort('name', true);
    }

    public function listAction(mixed $modelId = null, array $relations = []): void
    {
        Noerd::modal('noerd::user-role-detail', ['modelId' => $modelId, 'relations' => $relations]);
    }

    public function with(): array
    {
        $rows = $this->listQuery(UserRole::class)
            ->where('tenant_id', auth()->user()->selected_tenant_id)
            ->paginate($this->perPage);

        return [
            'listConfig' => $this->buildList($rows),
        ];
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

<x-noerd::page :disableModal="$disableModal">

    <x-noerd::list />

</x-noerd::page>
