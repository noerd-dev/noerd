<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Noerd\Models\User;
use Noerd\Traits\NoerdList;

new class extends Component {
    use NoerdList;

    public const DETAIL_COMPONENT = 'users-list';

    public function listAction(mixed $modelId = null, mixed $relationId = null): void
    {
        $this->dispatch(
            event: 'noerdModal',
            modalComponent: 'user-detail',
            source: $this->getComponentName(),
            arguments: ['modelId' => $modelId, 'relationId' => $relationId],
        );
    }

    public function loginAsUser($userId)
    {
        if (! Auth::user()->isAdmin()) {
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
        })->when($this->search, function ($query): void {
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
        if ((int) request()->id) {
            $this->listAction(request()->id);
        }

        if (request()->create) {
            $this->listAction();
        }
    }
}; ?>

<x-noerd::page :disableModal="$disableModal">

    <x-noerd::list />

</x-noerd::page>
