<?php

use Livewire\Component;
use Noerd\Models\SetupLanguage;
use Noerd\Traits\NoerdList;

new class extends Component
{
    use NoerdList;

    public function mount(): void
    {
        $this->mountList();

        // Ensure default languages exist for current tenant
        SetupLanguage::ensureDefaultLanguagesForTenant(auth()->user()->selected_tenant_id);

        if (request()->create) {
            $this->listAction();
        }
    }

    public function listAction(mixed $modelId = null, array $relations = []): void
    {
        $this->dispatch(
            event: 'noerdModal',
            modalComponent: 'noerd::setup-language-detail',
            source: $this->getComponentName(),
            arguments: ['modelId' => $modelId, 'relations' => $relations],
        );
    }

    public function with(): array
    {
        // Custom sort order: is_default desc, sort_order, name
        $rows = SetupLanguage::query()
            ->when($this->search, fn ($query) => $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('code', 'like', '%' . $this->search . '%');
            }))
            ->orderBy('is_default', 'desc')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($this->perPage);

        return [
            'listConfig' => $this->buildList($rows),
        ];
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-noerd::list />
</x-noerd::page>
