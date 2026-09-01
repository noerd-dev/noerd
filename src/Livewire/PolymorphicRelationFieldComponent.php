<?php

namespace Noerd\Livewire;

use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Noerd\Facades\Noerd;
use Noerd\Services\RelationFieldRegistry;
use Noerd\Support\RelationFieldDefinition;

/**
 * Shared behaviour of every polymorphic relation field view variant. The
 * variants differ ONLY in their markup: each single-file component is
 * `new class extends PolymorphicRelationFieldComponent {}` plus its Blade.
 */
abstract class PolymorphicRelationFieldComponent extends Component
{
    // Mount-established identity/config is #[Locked] — a crafted update must
    // never repoint the field at another form key or widen the type whitelist.
    // $value, $currentType, $selectedRelationType and $displayTitle stay
    // mutable: they carry the user's selection.
    #[Locked]
    public string $fieldName = '';

    #[Locked]
    public string $typeField = '';

    #[Locked]
    public string $label = '';

    public mixed $value = null;

    public ?string $currentType = null;

    /** @var array<int, string> */
    #[Locked]
    public array $allowedTypes = [];

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

    public string $selectedRelationType = '';

    public string $displayTitle = '';

    /**
     * @param  array<int, string>  $allowedTypes
     */
    /** Livewire id of the owning detail — see RelationFieldComponent::$owner. */
    #[Locked]
    public ?string $owner = null;

    public function mount(
        string $fieldName,
        string $typeField,
        string $label = '',
        mixed $value = null,
        ?string $currentType = null,
        array $allowedTypes = [],
        bool $required = false,
        bool $readonly = false,
        mixed $modelId = null,
        ?int $number = null,
        string $theme = 'default',
        string $helpText = '',
        ?string $owner = null,
    ): void {
        $this->owner = $owner;
        $this->fieldName = $fieldName;
        $this->typeField = $typeField;
        $this->label = $label;
        $this->value = $value;
        $this->currentType = $currentType;
        $this->allowedTypes = $allowedTypes;
        $this->required = $required;
        $this->readonly = $readonly;
        $this->helpText = $helpText;
        $this->modelId = $modelId;
        $this->number = $number;
        $this->theme = $theme;

        $this->selectedRelationType = $this->resolveRelationTypeFromModelType($currentType) ?? '';
        $this->resolveDisplayTitle();
    }

    /**
     * The `context` a picker opened by this field reports back with — the
     * field name plus the owner id (see RelationFieldComponent::selectionContext()).
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

        if (! $this->acceptsSelectionContext($context)) {
            return;
        }

        $definition = $this->activeDefinition();
        if (!$definition) {
            return;
        }

        $this->value = $value;
        $this->currentType = $definition->modelClass;
        $this->resolveDisplayTitle();

        $this->dispatch(
            'setFieldValue',
            field: $this->typeField,
            value: $this->currentType,
            owner: $this->owner,
        );
        $this->dispatch(
            'setFieldValue',
            field: $this->fieldName,
            value: $this->value,
            relationTitle: $this->displayTitle,
            owner: $this->owner,
        );
    }

    public function updatedSelectedRelationType(string $value): void
    {
        // The list component is memoized per request — the type just changed.
        unset($this->activeListComponent);

        if ($this->readonly) {
            return;
        }

        $definition = $this->activeDefinition();
        if (!$definition) {
            return;
        }

        if ($this->currentType === $definition->modelClass) {
            return;
        }

        $this->value = null;
        $this->currentType = null;
        $this->displayTitle = '';

        $this->dispatch('setFieldValue', field: $this->typeField, value: null, owner: $this->owner);
        $this->dispatch('setFieldValue', field: $this->fieldName, value: null, owner: $this->owner);
    }

    public function clear(): void
    {
        if ($this->readonly) {
            return;
        }

        $this->value = null;
        $this->currentType = null;
        $this->selectedRelationType = '';
        $this->displayTitle = '';

        $this->dispatch('setFieldValue', field: $this->typeField, value: null, owner: $this->owner);
        $this->dispatch('setFieldValue', field: $this->fieldName, value: null, owner: $this->owner);
    }

    public function openDetail(): void
    {
        $definition = $this->activeDefinition();
        if (!$definition || !$this->value) {
            return;
        }

        Noerd::modalFor($definition->detailRoute, $definition->getDetailComponent(), ['modelId' => $this->value]);
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function typeOptions(): array
    {
        $registry = app(RelationFieldRegistry::class);
        $options = ['' => __('Select Type')];

        foreach ($this->allowedTypes as $type) {
            $definition = $registry->resolve($type);
            if (!$definition) {
                continue;
            }

            $options[$type] = $this->labelForRelationType($type);
        }

        return $options;
    }

    #[Computed]
    public function activeListComponent(): ?string
    {
        return $this->activeDefinition()?->listComponent;
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

    private function activeDefinition(): ?RelationFieldDefinition
    {
        // $selectedRelationType is client-writable by design (the user picks the
        // type), so it must be re-checked against the field's declared
        // whitelist — resolving it straight from the registry let a crafted
        // update point the polymorphic FK at a model the YAML forbids.
        if ($this->allowedTypes !== [] && ! in_array($this->selectedRelationType, $this->allowedTypes, true)) {
            return null;
        }

        if ($this->selectedRelationType === '') {
            return null;
        }

        return app(RelationFieldRegistry::class)->resolve($this->selectedRelationType);
    }

    private function resolveRelationTypeFromModelType(?string $modelType): ?string
    {
        if (!$modelType) {
            return null;
        }

        $registry = app(RelationFieldRegistry::class);
        foreach ($this->allowedTypes as $type) {
            $definition = $registry->resolve($type);
            if ($definition && $definition->modelClass === $modelType) {
                return $type;
            }
        }

        return null;
    }

    private function resolveDisplayTitle(): void
    {
        $definition = $this->activeDefinition();
        if (!$definition || $this->value === null || $this->value === '') {
            $this->displayTitle = '';

            return;
        }

        $this->displayTitle = $definition->resolveTitleForValue($this->value);
    }

    private function labelForRelationType(string $type): string
    {
        $stripped = Str::endsWith($type, 'Relation') ? Str::beforeLast($type, 'Relation') : $type;

        return __(Str::headline($stripped));
    }
}
