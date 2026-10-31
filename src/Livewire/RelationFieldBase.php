<?php

declare(strict_types=1);

namespace Noerd\Livewire;

use Livewire\Attributes\Locked;
use Livewire\Attributes\Reactive;
use Livewire\Component;
use Noerd\Facades\Noerd;
use Noerd\Support\RelationFieldDefinition;

/**
 * The props and behaviour every relation field component shares — the plain FK
 * field (RelationFieldComponent) and the polymorphic one
 * (PolymorphicRelationFieldComponent): the shared mount, the selection guard,
 * clearing, opening the related record, resolving the display title and the
 * sync into the owning detail. The subclasses only say which relation type
 * definition is active. Mount-established identity/config is #[Locked]
 * throughout: a crafted update must never repoint a field at another form key,
 * list, detail target or event.
 */
abstract class RelationFieldBase extends Component
{
    #[Locked]
    public string $fieldName = '';

    #[Locked]
    public string $label = '';

    public mixed $value = null;

    #[Locked]
    public bool $required = false;

    #[Locked]
    public bool $readonly = false;

    /** Optional YAML `helpText`, rendered as a tooltip next to the label. */
    #[Locked]
    public string $helpText = '';

    #[Locked]
    public mixed $modelId = null;

    /** Row number supplied by the detail block in themes that number their rows. */
    #[Locked]
    public ?int $number = null;

    /** Theme the owning detail block renders in — selects the element template. */
    #[Locked]
    public string $theme = 'default';

    public string $displayTitle = '';

    /**
     * Livewire id of the detail/page that owns this field. Scopes the
     * `setFieldValue` sync and the picker context to that one component, so
     * two stacked details sharing a field name never adopt each other's value.
     */
    #[Locked]
    public ?string $owner = null;

    /**
     * Validation messages of the owning detail for this field — reactive, so
     * a failed store() shows them without re-mounting the field.
     *
     * @var array<int, string>
     */
    #[Reactive]
    public array $errorMessages = [];

    /**
     * The relation type definition behind the current value — null while none
     * applies (a polymorphic field without a chosen type).
     */
    abstract protected function activeDefinition(): ?RelationFieldDefinition;

    /**
     * Empty the field: a hidden affordance is no guard, so a readonly field
     * never mutates — also when the method is called directly.
     */
    public function clear(): void
    {
        if ($this->readonly) {
            return;
        }

        $this->value = null;
        $this->displayTitle = '';
        $this->syncField($this->fieldName, null, '');
    }

    /**
     * Open the related record by route, with the detail component as fallback.
     */
    public function openDetail(): void
    {
        $definition = $this->activeDefinition();

        if (! $definition || ! $this->value) {
            return;
        }

        Noerd::modalFor($definition->detailRoute, $definition->getDetailComponent(), ['modelId' => $this->value]);
    }

    /**
     * The `context` a picker opened by this field reports back with. It carries
     * the owner id so a selection only lands in the field that opened the
     * picker — a custom renderer opens its picker with exactly this value.
     */
    public function selectionContext(): string
    {
        return $this->owner ? $this->fieldName . '@' . $this->owner : $this->fieldName;
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

    /**
     * The props every variant receives from the detail block.
     */
    protected function mountBase(
        string $fieldName,
        string $label,
        mixed $value,
        bool $required,
        bool $readonly,
        mixed $modelId,
        ?int $number,
        string $theme,
        string $helpText,
        ?string $owner,
    ): void {
        $this->fieldName = $fieldName;
        $this->label = $label;
        $this->value = $value;
        $this->required = $required;
        $this->readonly = $readonly;
        $this->modelId = $modelId;
        $this->number = $number;
        $this->theme = $theme;
        $this->helpText = $helpText;
        $this->owner = $owner;
    }

    /**
     * Whether a picker's selection may land here: never in a readonly field,
     * and only from the picker THIS field opened — a picker opened without a
     * context dispatches '' / null, which must not be adopted by every relation
     * field on the page.
     */
    protected function acceptsSelection(?string $context): bool
    {
        return ! $this->readonly && $this->acceptsSelectionContext($context);
    }

    protected function acceptsSelectionContext(?string $context): bool
    {
        return $context !== null && $context === $this->selectionContext();
    }

    protected function resolveDisplayTitle(): void
    {
        $definition = $this->activeDefinition();

        $this->displayTitle = $definition && $this->value !== null && $this->value !== ''
            ? $definition->resolveTitleForValue($this->value)
            : '';
    }

    /**
     * Write a value into the owning detail's form bucket (`setFieldValue`).
     */
    protected function syncField(string $field, mixed $value, ?string $relationTitle = null): void
    {
        $this->dispatch(
            'setFieldValue',
            field: $field,
            value: $value,
            relationTitle: $relationTitle,
            owner: $this->owner,
        );
    }
}
