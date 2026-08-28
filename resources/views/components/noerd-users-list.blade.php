<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Traits\NoerdList;
use Noerd\Traits\TenantFilterTrait;

new class () extends Component {
    use NoerdList;
    use TenantFilterTrait;

    public const DETAIL_COMPONENT = 'noerd::noerd-users-list';

    public $listModel = NoerdUser::class;
    public ?string $detailRoute = 'noerd.user.detail';

    public function mount(): void
    {
        $this->mountList();
        $this->setDefaultSort('name', true);
    }

    public function loginAsUser($userId)
    {
        if (! Auth::user()->isAdmin()) {
            abort(401);
        }

        $tenants = Auth::user()->adminTenants();
        $allowedUserIds = NoerdUser::whereHas('tenants', function ($relationQuery) use ($tenants): void {
            $relationQuery->whereIn('tenant_id', $tenants->pluck('id'));
        })->get()->pluck('id')->toArray();

        if (in_array($userId, $allowedUserIds) === false) {
            abort(401);
        }

        // A super admin is never impersonatable from a tenant-admin screen:
        // attaching such an account to one's own tenant would otherwise be a
        // direct path to a full takeover.
        abort_if(NoerdUser::whereKey($userId)->value('super_admin'), 403);
        session(['impersonating_from' => NoerdAuth::id()]);

        // Clear tenant session so InitializeTenantSession will set the correct tenant
        TenantHelper::clear();

        NoerdAuth::guard()->loginUsingId($userId);

        return redirect('/');
    }

    public function listData(): array
    {
        $tenants = Auth::user()->adminTenants();

        $rows = $this->listQuery($this->listModel)
            ->whereHas('tenants', function ($relationQuery) use ($tenants): void {
                $adminTenantIds = $tenants->pluck('id')->map(fn($id): int => (int) $id)->all();

                // The header filter is client input (it round-trips through the
                // session), so it may only ever NARROW the admin's own tenants —
                // taking it at face value listed the users of any tenant.
                $requested = (int) ($this->listFilters['tenant_id'] ?? 0);
                $scope = $requested > 0
                    ? array_values(array_intersect($adminTenantIds, [$requested]))
                    : $adminTenantIds;

                $relationQuery->whereIn('tenant_id', $scope ?: [0]);
            })
            ->with(['tenants'])
            ->paginate($this->perPage);

        return $this->buildList($rows);
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

<x-noerd::page>

    <x-noerd::list />

</x-noerd::page>
