<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Noerd\Models\UserRole;
use Noerd\Traits\NoerdList;

new class extends Component {
    use NoerdList;

    public function listAction(mixed $modelId = null, mixed $relationId = null): void
    {
        $this->dispatch(
            event: 'noerdModal',
            modalComponent: 'user-role-detail',
            source: $this->getComponentName(),
            arguments: ['modelId' => $modelId, 'relationId' => $relationId],
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
