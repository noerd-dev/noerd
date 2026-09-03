<?php

declare(strict_types=1);

namespace Noerd\Livewire;

use Livewire\Attributes\Locked;
use Livewire\Attributes\Reactive;
use Livewire\Component;

/**
 * The props and helpers every relation field component shares — the plain FK
 * field (RelationFieldComponent) and the polymorphic one
 * (PolymorphicRelationFieldComponent). Mount-established identity/config is
 * #[Locked] throughout: a crafted update must never repoint a field at another
 * form key, list, detail target or event.
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
     * The `context` a picker opened by this field reports back with. It carries
     * the owner id so a selection only lands in the field that opened the
     * picker; the plain field name is still accepted for custom renderers.
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

    protected function acceptsSelectionContext(?string $context): bool
    {
        return $context !== null && in_array($context, [$this->fieldName, $this->selectionContext()], true);
    }
}
