<?php

use Livewire\Component;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Support\Impersonation;
use Noerd\Support\ListFilter;
use Noerd\Traits\NoerdList;

new class extends Component {
    use NoerdList;

    public $listModel = NoerdUser::class;
    public ?string $detailRoute = 'noerd.user.detail';

    public $detailComponent = 'noerd::noerd-user-page';

    /** The filter value of the super admin's "Without tenant" option. */
    private const string WITHOUT_TENANT = 'none';

    /**
     * The tenant dropdown mirrors the list scope: every tenant for a super
     * admin — plus "Without tenant", the accounts that lost their last
     * membership —, the administered tenants for a tenant admin. Guest-safe — a
     * list rendered without an authenticated noerd user offers an empty
     * picklist. The membership is a pivot, not a column: listData() applies
     * the choice.
     */
    protected function getTenantsListFilter(): ListFilter
    {
        return ListFilter::options(
            __('Tenant'),
            'tenant_id',
            function (): array {
                $user = NoerdAuth::user();
                $options = ($user?->administeredTenants() ?? collect())->pluck('name', 'id')->all();

                if ($user?->isSuperAdmin()) {
                    $options[self::WITHOUT_TENANT] = __('Without tenant');
                }

                return $options;
            },
        )->manual();
    }

    /** Shown above the list when "Login as user" needs another tenant selected. */
    public ?string $impersonationNotice = null;

    public function loginAsUser(int $userId): void
    {
        $target = NoerdUser::find($userId);

        abort_unless($target instanceof NoerdUser, 403);

        // An impersonation is bound to the selected tenant: a member of another
        // tenant is explained, not answered with a bare 403.
        $tenantId = TenantHelper::getSelectedTenantId();

        if (! $target->isSuperAdmin() && ($tenantId === null || ! $target->tenants()->whereKey($tenantId)->exists())) {
            $this->impersonationNotice = __('This user does not belong to the selected tenant. Select one of their tenants to sign in as them.');

            return;
        }

        Impersonation::start($target);

        $this->redirect('/');
    }

    public function listData(): array
    {
        $filterValue = $this->listFilters['tenant_id'] ?? null;
        $requested = (int) $filterValue;

        $query = $this->listQuery($this->listModel)->with(['tenants']);

        if (NoerdAuth::user()->isSuperAdmin()) {
            // A super admin administers the whole installation: every account is
            // visible, including one that belongs to no tenant at all. Only the
            // header filter narrows the list.
            if ($filterValue === self::WITHOUT_TENANT) {
                $query->whereDoesntHave('tenants');
            } elseif ($requested > 0) {
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
    @if ($impersonationNotice)
        <div class="mx-4 mt-3 rounded-md bg-amber-50 px-4 py-2 text-sm text-amber-800" role="alert">
            {{ $impersonationNotice }}
        </div>
    @endif

    <x-noerd::list />
</x-noerd::page>
