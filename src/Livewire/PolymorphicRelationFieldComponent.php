<?php

namespace Noerd\Livewire;

use Illuminate\Support\Str;
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
    public string $fieldName = '';

    public string $typeField = '';

    public string $label = '';

    public mixed $value = null;

    public ?string $currentType = null;

    /** @var array<int, string> */
    public array $allowedTypes = [];

    public bool $required = false;

    public bool $readonly = false;

    /** Optional YAML `helpText`, rendered as a tooltip next to the label. */
    public string $helpText = '';

    public mixed $modelId = null;

    /** Row number supplied by the detail block in themes that number their rows. */
    public ?int $number = null;

    /** Theme the owning detail block renders in — selects the element template. */
    public string $theme = 'default';

    public string $selectedRelationType = '';

    public string $displayTitle = '';

    /**
     * @param  array<int, string>  $allowedTypes
     */
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
    ): void {
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

    #[On('noerdRelationSelected')]
    public function relationSelected(mixed $value, ?string $context = null): void
    {
        if ($context !== $this->fieldName) {
            return;
        }

        $definition = $this->activeDefinition();
        if (! $definition) {
            return;
        }

        $this->value = $value;
        $this->currentType = $definition->modelClass;
        $this->resolveDisplayTitle();

        $this->dispatch(
            'setFieldValue',
            field: $this->typeField,
            value: $this->currentType,
        );
        $this->dispatch(
            'setFieldValue',
            field: $this->fieldName,
            value: $this->value,
            relationTitle: $this->displayTitle,
        );
    }

    public function updatedSelectedRelationType(string $value): void
    {
        $definition = $this->activeDefinition();
        if (! $definition) {
            return;
        }

        if ($this->currentType === $definition->modelClass) {
            return;
        }

        $this->value = null;
        $this->currentType = null;
        $this->displayTitle = '';

        $this->dispatch('setFieldValue', field: $this->typeField, value: null);
        $this->dispatch('setFieldValue', field: $this->fieldName, value: null);
    }

    public function clear(): void
    {
        $this->value = null;
        $this->currentType = null;
        $this->selectedRelationType = '';
        $this->displayTitle = '';

        $this->dispatch('setFieldValue', field: $this->typeField, value: null);
        $this->dispatch('setFieldValue', field: $this->fieldName, value: null);
    }

    public function openDetail(): void
    {
        $definition = $this->activeDefinition();
        if (! $definition || ! $this->value) {
            return;
        }

        Noerd::modalFor($definition->detailRoute, $definition->getDetailComponent(), ['modelId' => $this->value]);
    }

    /**
     * @return array<string, string>
     */
    public function getTypeOptionsProperty(): array
    {
        $registry = app(RelationFieldRegistry::class);
        $options = ['' => __('Select Type')];

        foreach ($this->allowedTypes as $type) {
            $definition = $registry->resolve($type);
            if (! $definition) {
                continue;
            }

            $options[$type] = $this->labelForRelationType($type);
        }

        return $options;
    }

    public function getActiveListComponentProperty(): ?string
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

    private function activeDefinition(): ?RelationFieldDefinition
    {
        if ($this->selectedRelationType === '') {
            return null;
        }

        return app(RelationFieldRegistry::class)->resolve($this->selectedRelationType);
    }

    private function resolveRelationTypeFromModelType(?string $modelType): ?string
    {
        if (! $modelType) {
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
        if (! $definition || $this->value === null || $this->value === '') {
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
