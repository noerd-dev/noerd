<?php

use Livewire\Component;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Traits\NoerdList;
use Noerd\Traits\TenantFilterTrait;

new class extends Component {
    use NoerdList;
    use TenantFilterTrait;

    public const DETAIL_COMPONENT = 'noerd::noerd-users-list';
    public $listModel = NoerdUser::class;
    public ?string $detailRoute = 'noerd.user.detail';

    public function loginAsUser($userId)
    {
        if (! NoerdAuth::user()->isAdmin()) {
            abort(401);
        }

        // A super admin administers the installation, so every account it can
        // see on this screen is impersonatable; a tenant admin stays confined
        // to the members of the tenants it administers.
        if (! NoerdAuth::user()->isSuperAdmin()) {
            $tenants = NoerdAuth::user()->adminTenants();
            $allowedUserIds = NoerdUser::whereHas('tenants', function ($relationQuery) use ($tenants): void {
                $relationQuery->whereIn('tenant_id', $tenants->pluck('id'));
            })->get()->pluck('id')->toArray();

            if (in_array($userId, $allowedUserIds) === false) {
                abort(401);
            }
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
        $requested = (int) ($this->listFilters['tenant_id'] ?? 0);

        $query = $this->listQuery($this->listModel)->with(['tenants']);

        if (NoerdAuth::user()->isSuperAdmin()) {
            // A super admin administers the whole installation: every account is
            // visible, including one that belongs to no tenant at all. Only the
            // header filter narrows the list.
            if ($requested > 0) {
                $query->whereHas('tenants', function ($relationQuery) use ($requested): void {
                    $relationQuery->where('tenant_id', $requested);
                });
            }
        } else {
            $tenants = NoerdAuth::user()->adminTenants();

            $query->whereHas('tenants', function ($relationQuery) use ($tenants, $requested): void {
                $adminTenantIds = $tenants->pluck('id')->map(fn($id): int => (int) $id)->all();
                $scope = $requested > 0
                    ? array_values(array_intersect($adminTenantIds, [$requested]))
                    : $adminTenantIds;

                $relationQuery->whereIn('tenant_id', $scope ?: [0]);
            });
        }

        return $this->buildList($query->paginate($this->perPage));
    }
}; ?>

<x-noerd::page>
    <x-noerd::list />
</x-noerd::page>
