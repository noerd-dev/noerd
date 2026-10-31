<?php

declare(strict_types=1);

namespace Noerd\Livewire;

use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Noerd\Services\RelationFieldRegistry;
use Noerd\Support\RelationFieldDefinition;
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
        $definition = app(RelationFieldRegistry::class)->get($relationType);

        if (! $definition) {
            throw new RuntimeException("Relation field type [{$relationType}] is not registered.");
        }

        $this->mountBase($fieldName, $label, $value, $required, $readonly, $modelId, $number, $theme, $helpText, $owner);

        $this->relationType = $relationType;
        $this->listComponent = $definition->listComponent;
        $this->detailComponent = $definition->getDetailComponent();
        $this->detailRoute = $definition->detailRoute;
        $this->selectEvent = $definition->getSelectEvent();

        $this->resolveDisplayTitle();
    }

    #[On('noerdRelationSelected')]
    public function relationSelected(mixed $value, ?string $context = null): void
    {
        if (! $this->acceptsSelection($context)) {
            return;
        }

        $this->value = $value;
        $this->resolveDisplayTitle();
        $this->syncField($this->fieldName, $this->value, $this->displayTitle);

        if ($this->selectEvent) {
            $this->dispatch($this->selectEvent, $this->value, $this->fieldName);
        }
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

        $definition = $this->activeDefinition();

        if (! $definition?->modelClass || ! class_exists($definition->modelClass)) {
            return null;
        }

        return $definition->modelClass::query()->find($this->value);
    }

    protected function activeDefinition(): ?RelationFieldDefinition
    {
        return app(RelationFieldRegistry::class)->get($this->relationType);
    }
}
