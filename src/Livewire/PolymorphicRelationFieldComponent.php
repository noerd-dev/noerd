<?php

declare(strict_types=1);

namespace Noerd\Livewire;

use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Noerd\Services\RelationFieldRegistry;
use Noerd\Support\RelationFieldDefinition;

/**
 * Shared behaviour of every polymorphic relation field view variant. The
 * variants differ ONLY in their markup: each single-file component is
 * `new class extends PolymorphicRelationFieldComponent {}` plus its Blade.
 */
abstract class PolymorphicRelationFieldComponent extends RelationFieldBase
{
    // $currentType and $selectedRelationType stay mutable on purpose: they
    // carry the user's type choice, re-checked against $allowedTypes on use.
    #[Locked]
    public string $typeField = '';

    public ?string $currentType = null;

    /** @var array<int, string> */
    #[Locked]
    public array $allowedTypes = [];

    public string $selectedRelationType = '';

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
        ?string $owner = null,
    ): void {
        $this->mountBase($fieldName, $label, $value, $required, $readonly, $modelId, $number, $theme, $helpText, $owner);

        $this->typeField = $typeField;
        $this->currentType = $currentType;
        $this->allowedTypes = $allowedTypes;

        $this->selectedRelationType = $this->resolveRelationTypeFromModelType($currentType) ?? '';
        $this->resolveDisplayTitle();
    }

    #[On('noerdRelationSelected')]
    public function relationSelected(mixed $value, ?string $context = null): void
    {
        if (! $this->acceptsSelection($context)) {
            return;
        }

        $definition = $this->activeDefinition();
        if (! $definition) {
            return;
        }

        $this->value = $value;
        $this->currentType = $definition->modelClass;
        $this->resolveDisplayTitle();

        $this->syncField($this->typeField, $this->currentType);
        $this->syncField($this->fieldName, $this->value, $this->displayTitle);
    }

    public function updatedSelectedRelationType(string $value): void
    {
        // The list component is memoized per request — the type just changed.
        unset($this->activeListComponent);

        if ($this->readonly) {
            return;
        }

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

        $this->syncField($this->typeField, null);
        $this->syncField($this->fieldName, null);
    }

    public function clear(): void
    {
        if ($this->readonly) {
            return;
        }

        $this->currentType = null;
        $this->selectedRelationType = '';
        $this->syncField($this->typeField, null);

        parent::clear();
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
            $definition = $registry->get($type);
            if (! $definition) {
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

    protected function activeDefinition(): ?RelationFieldDefinition
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

        return app(RelationFieldRegistry::class)->get($this->selectedRelationType);
    }

    private function resolveRelationTypeFromModelType(?string $modelType): ?string
    {
        if (! $modelType) {
            return null;
        }

        $registry = app(RelationFieldRegistry::class);
        foreach ($this->allowedTypes as $type) {
            $definition = $registry->get($type);
            if ($definition && $definition->modelClass === $modelType) {
                return $type;
            }
        }

        return null;
    }

    private function labelForRelationType(string $type): string
    {
        $stripped = Str::endsWith($type, 'Relation') ? Str::beforeLast($type, 'Relation') : $type;

        return __(Str::headline($stripped));
    }
}
