<?php

use Livewire\Volt\Component;
use Noerd\Noerd\Helpers\StaticConfigHelper;
use Noerd\Noerd\Models\SetupLanguage;
use Noerd\Noerd\Traits\Noerd;

new class extends Component
{
    use Noerd;

    public const COMPONENT = 'setup-languages-list';

    public function mount(): void
    {
        // Ensure default languages exist
        SetupLanguage::ensureDefaultLanguages();

        if (request()->create) {
            $this->tableAction();
        }
    }

    public function tableAction(mixed $modelId = null, mixed $relationId = null): void
    {
        $this->dispatch(
            event: 'noerdModal',
            component: 'setup-language-detail',
            source: self::COMPONENT,
            arguments: ['modelId' => $modelId, 'relationId' => $relationId],
        );
    }

    public function with(): array
    {
        $rows = SetupLanguage::query()
            ->orderBy('is_default', 'desc')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->when($this->search, function ($query): void {
                $query->where(function ($query): void {
                    $query->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('code', 'like', '%'.$this->search.'%');
                });
            })
            ->paginate(self::PAGINATION);

        $tableConfig = $this->getTableConfig();

        return [
            'rows' => $rows,
            'tableConfig' => $tableConfig,
        ];
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <div>
        @include('noerd::components.table.table-build', ['tableConfig' => $tableConfig])
    </div>
</x-noerd::page>
