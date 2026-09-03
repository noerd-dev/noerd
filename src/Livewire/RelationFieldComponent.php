<?php

declare(strict_types=1);

namespace Noerd\Livewire;

use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Noerd\Facades\Noerd;
use Noerd\Services\RelationFieldRegistry;
use RuntimeException;

/**
 * Shared behaviour of every relation field view variant (default, compact,
 * numbered, …). The variants differ ONLY in their markup: each single-file
 * component is `new class extends RelationFieldComponent {}` plus its Blade.
 */
abstract class RelationFieldComponent extends RelationFieldBase
{
    // The registered relation type and everything derived from it are
    // mount-established identity/config — #[Locked] like the shared props.
    #[Locked]
    public string $relationType = '';

    #[Locked]
    public string $listComponent = '';

    #[Locked]
    public ?string $detailComponent = null;

    #[Locked]
    public ?string $detailRoute = null;

    #[Locked]
    public ?string $selectEvent = null;

    public function mount(
        string $relationType,
        string $fieldName,
        string $label = '',
        mixed $value = null,
        bool $required = false,
        bool $readonly = false,
        mixed $modelId = null,
        ?int $number = null,
        string $theme = 'default',
        string $helpText = '',
        ?string $owner = null,
    ): void {
        $definition = app(RelationFieldRegistry::class)->resolve($relationType);

        if (! $definition) {
            throw new RuntimeException("Relation field type [{$relationType}] is not registered.");
        }

        $this->relationType = $relationType;
        $this->fieldName = $fieldName;
        $this->label = $label;
        $this->value = $value;
        $this->required = $required;
        $this->readonly = $readonly;
        $this->helpText = $helpText;
        $this->modelId = $modelId;
        $this->number = $number;
        $this->theme = $theme;
        $this->listComponent = $definition->listComponent;
        $this->detailComponent = $definition->getDetailComponent();
        $this->detailRoute = $definition->detailRoute;
        $this->selectEvent = $definition->getSelectEvent();
        $this->owner = $owner;

        $this->resolveDisplayTitle();
    }

    #[On('noerdRelationSelected')]
    public function relationSelected(mixed $value, ?string $context = null): void
    {
        // Hidden affordances are no guard — a readonly field never mutates.
        if ($this->readonly) {
            return;
        }

        // Strict match: a picker opened without a context dispatches '' / null,
        // which must not be adopted by every relation field on the page.
        if (! $this->acceptsSelectionContext($context)) {
            return;
        }

        $this->value = $value;
        $this->resolveDisplayTitle();
        $this->syncParentState();

        if ($this->selectEvent) {
            $this->dispatch($this->selectEvent, $this->value, $this->fieldName);
        }
    }

    public function clear(): void
    {
        if ($this->readonly) {
            return;
        }

        $this->value = null;
        $this->displayTitle = '';
        $this->syncParentState();
    }

    public function openDetail(): void
    {
        if (! $this->value) {
            return;
        }

        Noerd::modalFor($this->detailRoute, $this->detailComponent, ['modelId' => $this->value]);
    }

    /**
     * The related Eloquent model behind the current value — for custom renderer
     * components (fieldComponent) that display more than the title. Computed,
     * so a view reading it several times issues ONE query.
     */
    #[Computed]
    public function relatedModel(): ?Model
    {
        if (! $this->value) {
            return null;
        }

        $definition = app(RelationFieldRegistry::class)->resolve($this->relationType);

        if (! $definition?->modelClass || ! class_exists($definition->modelClass)) {
            return null;
        }

        return $definition->modelClass::query()->find($this->value);
    }

    private function resolveDisplayTitle(): void
    {
        $definition = app(RelationFieldRegistry::class)->resolve($this->relationType);

        $this->displayTitle = $definition?->resolveTitleForValue($this->value) ?? '';
    }

    private function syncParentState(): void
    {
        $this->dispatch(
            'setFieldValue',
            field: $this->fieldName,
            value: $this->value,
            relationTitle: $this->displayTitle,
            owner: $this->owner,
        );
    }
}
