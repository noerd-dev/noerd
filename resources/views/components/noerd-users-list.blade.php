<?php

use Livewire\Component;
use Noerd\Helpers\NoerdAuth;
use Noerd\Models\NoerdUser;
use Noerd\Support\Impersonation;
use Noerd\Traits\NoerdList;
use Noerd\Traits\TenantFilterTrait;

new class extends Component {
    use NoerdList;
    use TenantFilterTrait;

    public $listModel = NoerdUser::class;
    public ?string $detailRoute = 'noerd.user.detail';

    public $detailComponent = 'noerd::noerd-user-page';

    public function loginAsUser(int $userId): void
    {
        $target = NoerdUser::find($userId);

        abort_unless($target instanceof NoerdUser, 403);

        Impersonation::start($target);

        $this->redirect('/');
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
