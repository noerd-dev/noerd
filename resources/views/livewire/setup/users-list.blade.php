<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Noerd\Noerd\Helpers\StaticConfigHelper;
use Noerd\Noerd\Models\User;
use Noerd\Noerd\Traits\Noerd;

new class extends Component {

    use Noerd;

    public const COMPONENT = 'users-list';

    public function mount()
    {
        $this->sortField = 'name';
        $this->sortAsc = true;

        if ((int)request()->customerId) {
            $this->tableAction(request()->customerId);
        }

        if (request()->create) {
            $this->tableAction();
        }
    }

    public function tableAction(mixed $modelId = null, mixed $relationId = null): void
    {
        $this->dispatch(
            event: 'noerdModal',
            component: 'user-detail',
            source: self::COMPONENT,
            arguments: ['modelId' => $modelId, 'relationId' => $relationId],
        );
    }

    public function loginAsUser($userId)
    {
        if (!Auth::user()->isAdmin()) {
            abort(401);
        }

        $tenants = Auth::user()->adminTenants();
        $allowedUserIds = User::whereHas('tenants', function ($relationQuery) use ($tenants) {
            $relationQuery->whereIn('tenant_id', $tenants->pluck('id'));
        })->get()->pluck('id')->toArray();

        if (in_array($userId, $allowedUserIds) === false) {
            abort(401);
        }
        Auth::loginUsingId($userId);

        return redirect('/');
    }

    public function removeFromTenant($id)
    {
        // TODO
    }

    public function with(): array
    {
        $tenants = Auth::user()->adminTenants();
        $rows = User::whereHas('tenants', function ($relationQuery) use ($tenants) {
            $relationQuery->whereIn('tenant_id', $tenants->pluck('id'));
        })->orderBy($this->sortField, $this->sortAsc ? 'asc' : 'desc')
            ->when($this->search, function ($query): void {
                $query->where(function ($query): void {
                    $query->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%');
                });
            })
            ->with('roles')
            ->paginate(self::PAGINATION);

        $tableConfig = StaticConfigHelper::getTableConfig('users-list');

        return [
            'rows' => $rows,
            'tableConfig' => $tableConfig,
        ];
    }

} ?>

<x-noerd::page :disableModal="$disableModal">
    @include('noerd::components.table.table-build', ['tableConfig' => $tableConfig])
</x-noerd::page>
