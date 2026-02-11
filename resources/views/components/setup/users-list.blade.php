<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Noerd\Models\User;
use Noerd\Traits\NoerdList;
use Noerd\Traits\TenantFilterTrait;

new class () extends Component {
    use NoerdList;
    use TenantFilterTrait;

    public const DETAIL_COMPONENT = 'users-list';

    public function listAction(mixed $modelId = null, array $relations = []): void
    {
        $this->dispatch(
            event: 'noerdModal',
            modalComponent: 'user-detail',
            source: $this->getComponentName(),
            arguments: ['modelId' => $modelId, 'relations' => $relations],
        );
    }

    public function loginAsUser($userId)
    {
        if (! Auth::user()->isAdmin()) {
            abort(401);
        }

        $tenants = Auth::user()->adminTenants();
        $allowedUserIds = User::whereHas('tenants', function ($relationQuery) use ($tenants): void {
            $relationQuery->whereIn('tenant_id', $tenants->pluck('id'));
        })->get()->pluck('id')->toArray();

        if (in_array($userId, $allowedUserIds) === false) {
            abort(401);
        }
        session(['impersonating_from' => Auth::id()]);
        Auth::loginUsingId($userId);

        return redirect('/');
    }

    public function with(): array
    {
        $tenants = Auth::user()->adminTenants();

        $rows = User::whereHas('tenants', function ($relationQuery) use ($tenants): void {
            if (! empty($this->listFilters['tenant_id'])) {
                $relationQuery->where('tenant_id', $this->listFilters['tenant_id']);
            } else {
                $relationQuery->whereIn('tenant_id', $tenants->pluck('id'));
            }
        })
            ->when($this->search, function ($query): void {
                $query->where(function ($query): void {
                    $query->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%');
                });
            })
            ->with(['roles', 'tenants'])
            ->paginate(self::PAGINATION);

        return [
            'listConfig' => $this->buildList($rows),
        ];
    }

    public function rendering(): void
    {
        $this->loadListFilters();

        if ((int) request()->userId) {
            $this->listAction(request()->userId);
        }

        if (request()->create) {
            $this->listAction();
        }
    }
}; ?>

<x-noerd::page :disableModal="$disableModal">

    <x-noerd::list />

</x-noerd::page>
