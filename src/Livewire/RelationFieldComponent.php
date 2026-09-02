<?php

declare(strict_types=1);

namespace Noerd\Livewire;

use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
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
    // Everything below except $value/$displayTitle is mount-established
    // identity/config — #[Locked], so a crafted update can never repoint the
    // field at another form key, list, detail target or event.
    #[Locked]
    public string $relationType = '';

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

    #[Locked]
    public string $listComponent = '';

    #[Locked]
    public ?string $detailComponent = null;

    #[Locked]
    public ?string $detailRoute = null;

    #[Locked]
    public ?string $selectEvent = null;

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

    /**
     * The `context` a picker opened by this field reports back with. It carries
     * the owner id so a selection only lands in the field that opened the
     * picker; the plain field name is still accepted for custom renderers.
     */
    public function selectionContext(): string
    {
        return $this->owner ? $this->fieldName . '@' . $this->owner : $this->fieldName;
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
     * The related Eloquent model behind the current value — for custom renderer
     * components (fieldComponent) that display more than the title.
     */
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

    protected function acceptsSelectionContext(?string $context): bool
    {
        return $context !== null && in_array($context, [$this->fieldName, $this->selectionContext()], true);
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
