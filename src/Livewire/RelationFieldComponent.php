<?php

namespace Noerd\Livewire;

use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Noerd\Facades\Noerd;
use Noerd\Services\RelationFieldRegistry;
use RuntimeException;

/**
 * Shared behaviour of every relation field view variant (default, compact,
 * numbered, …). The variants differ ONLY in their markup: each single-file
 * component is `new class extends RelationFieldComponent {}` plus its Blade.
 */
abstract class RelationFieldComponent extends Component
{
    public string $relationType = '';

    public string $fieldName = '';

    public string $label = '';

    public mixed $value = null;

    public bool $required = false;

    #[Locked]
    public bool $readonly = false;

    /** Optional YAML `helpText`, rendered as a tooltip next to the label. */
    public string $helpText = '';

    public mixed $modelId = null;

    /** Row number supplied by the detail block in themes that number their rows. */
    public ?int $number = null;

    /** Theme the owning detail block renders in — selects the element template. */
    public string $theme = 'default';

    public string $displayTitle = '';

    public string $listComponent = '';

    public ?string $detailComponent = null;

    public ?string $detailRoute = null;

    public ?string $legacySelectEvent = null;

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
    ): void {
        $definition = app(RelationFieldRegistry::class)->resolve($relationType);

        if (!$definition) {
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
        $this->legacySelectEvent = $definition->getSelectEvent();

        $this->resolveDisplayTitle();
    }

    #[On('noerdRelationSelected')]
    public function relationSelected(mixed $value, ?string $context = null): void
    {
        // Hidden affordances are no guard — a readonly field never mutates.
        if ($this->readonly) {
            return;
        }

        if ($context && $context !== $this->fieldName) {
            return;
        }

        $this->value = $value;
        $this->resolveDisplayTitle();
        $this->syncParentState();

        if ($this->legacySelectEvent) {
            $this->dispatch($this->legacySelectEvent, $this->value, $this->fieldName);
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
        if (!$this->value) {
            return;
        }

        Noerd::modalFor($this->detailRoute, $this->detailComponent, ['modelId' => $this->value]);
    }

    /**
     * The field array the numbered row chrome expects.
     *
     * @return array<string, mixed>
     */
    public function numberedRowField(): array
    {
        return [
            'name' => $this->fieldName,
            'label' => $this->label,
            'required' => $this->required,
            'number' => $this->number,
            'helpText' => $this->helpText,
        ];
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
        );
    }
}
