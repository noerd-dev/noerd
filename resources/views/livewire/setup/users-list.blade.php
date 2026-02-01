<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Noerd\Models\User;
use Noerd\Traits\Noerd;

new class extends Component {
    use Noerd;

    public const COMPONENT = 'users-list';

    public function listAction(mixed $userId = null, mixed $relationId = null): void
    {
        $this->dispatch(
            event: 'noerdModal',
            modalComponent: 'user-detail',
            source: self::COMPONENT,
            arguments: ['userId' => $userId, 'relationId' => $relationId],
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
