<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Noerd\Noerd\Models\UserRole;
use Noerd\Noerd\Traits\Noerd;

new class extends Component {
    use Noerd;

    public const COMPONENT = 'user-roles-list';

    public function listAction(mixed $modelId = null, mixed $relationId = null): void
    {
        $this->dispatch(
            event: 'noerdModal',
            modalComponent: 'user-role-detail',
            source: self::COMPONENT,
            arguments: ['userRoleId' => $modelId, 'relationId' => $relationId],
        );
    }

    public function with(): array
    {
        $rows = UserRole::where('tenant_id', auth()->user()->selected_tenant_id)
            ->orderBy('name')
            ->paginate(self::PAGINATION);

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
