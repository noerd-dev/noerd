<?php

use Livewire\Volt\Component;
use Noerd\Noerd\Models\SetupLanguage;
use Noerd\Noerd\Scopes\SortScope;
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
            modalComponent: 'setup-language-detail',
            source: self::COMPONENT,
            arguments: ['languageId' => $modelId, 'relationId' => $relationId],
        );
    }

    public function with(): array
    {
        // Custom sort order: is_default desc, sort_order, name
        $rows = SetupLanguage::withoutGlobalScope(SortScope::class)
            ->orderBy('is_default', 'desc')
            ->orderBy('sort_order')
            ->orderBy('name')
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
