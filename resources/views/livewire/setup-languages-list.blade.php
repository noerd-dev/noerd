<?php

use Livewire\Component;
use Noerd\Models\SetupLanguage;
use Noerd\Scopes\SortScope;
use Noerd\Traits\Noerd;

new class extends Component
{
    use Noerd;

    public const COMPONENT = 'setup-languages-list';

    public function mount(): void
    {
        // Ensure default languages exist
        SetupLanguage::ensureDefaultLanguages();

        if (request()->create) {
            $this->listAction();
        }
    }

    public function listAction(mixed $modelId = null, mixed $relationId = null): void
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

        return [
            'listConfig' => $this->buildList($rows),
        ];
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-noerd::list />
</x-noerd::page>
