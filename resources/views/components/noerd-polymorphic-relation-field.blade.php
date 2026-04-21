<?php

use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Noerd\Services\RelationFieldRegistry;

new class extends Component
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

    public mixed $modelId = null;

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
    ): void {
        $this->fieldName = $fieldName;
        $this->typeField = $typeField;
        $this->label = $label;
        $this->value = $value;
        $this->currentType = $currentType;
        $this->allowedTypes = $allowedTypes;
        $this->required = $required;
        $this->readonly = $readonly;
        $this->modelId = $modelId;

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

        $this->dispatch('setFieldValue',
            field: $this->typeField,
            value: $this->currentType,
        );
        $this->dispatch('setFieldValue',
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

        $detailComponent = $definition->getDetailComponent();
        if (! $detailComponent) {
            return;
        }

        $this->dispatch(
            event: 'noerdModal',
            modalComponent: $detailComponent,
            arguments: ['modelId' => $this->value],
        );
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

    private function activeDefinition(): ?\Noerd\Support\RelationFieldDefinition
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
}; ?>

<div>
    <x-noerd::input-label for="{{ $fieldName }}" :value="__($label)" :required="$required"/>
    <div class="grid grid-cols-12 gap-2">
        <div class="col-span-4">
            <select
                wire:model.live="selectedRelationType"
                class="w-full border rounded-lg block disabled:shadow-none appearance-none text-base sm:text-sm py-2 h-8 leading-[1.375rem] ps-3 pe-3 bg-white text-zinc-700 disabled:text-zinc-500 shadow-xs border-zinc-200 border-b-zinc-300/80 disabled:border-b-zinc-200 focus:outline-none focus:ring-2 focus:ring-brand-border focus:ring-offset-2"
                @if($readonly) disabled @endif
            >
                @foreach($this->typeOptions as $typeKey => $typeLabel)
                    <option value="{{ $typeKey }}">{{ $typeLabel }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-span-8">
            <div class="flex">
                <input
                    class="w-full cursor-pointer border rounded-lg block read-only:shadow-none appearance-none text-base sm:text-sm py-2 h-8 leading-[1.375rem] ps-3 pe-3 bg-white text-zinc-700 read-only:text-zinc-500 placeholder-zinc-400 read-only:placeholder-zinc-400/70 shadow-xs border-zinc-200 border-b-zinc-300/80 read-only:border-b-zinc-200 focus:outline-none focus:ring-2 focus:ring-brand-border focus:ring-offset-2"
                    type="text"
                    readonly
                    id="{{ $fieldName }}"
                    value="{{ $displayTitle }}"
                    @click="@if($displayTitle) $wire.openDetail() @elseif(! $readonly && $this->activeListComponent) $modal('{{ $this->activeListComponent }}', {id: null, context: '{{ $fieldName }}', listActionMethod: 'selectAction'}) @endif"
                >

                @if($displayTitle && ! $readonly)
                    <button
                        wire:click="clear"
                        class="h-8 inline-flex items-center px-2 !mt-0 !ml-1 text-zinc-400 hover:text-zinc-600"
                        type="button"
                    >
                        <x-noerd::icons.x-mark class="w-5 h-5"></x-noerd::icons.x-mark>
                    </button>
                @endif

                @if(! $readonly && $this->activeListComponent)
                    <x-noerd::button
                        @click="$modal('{{ $this->activeListComponent }}', {id: null, context: '{{ $fieldName }}', listActionMethod: 'selectAction'})"
                        class="h-8 rounded !mt-0 !ml-1"
                        type="button"
                    >
                        <x-noerd::icons.magnifying-glass></x-noerd::icons.magnifying-glass>
                    </x-noerd::button>
                @endif
            </div>
        </div>
    </div>
</div>
