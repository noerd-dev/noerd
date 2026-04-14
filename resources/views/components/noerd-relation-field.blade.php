<?php

use Livewire\Attributes\On;
use Livewire\Component;
use Noerd\Services\RelationFieldRegistry;

new class extends Component
{
    public string $relationType = '';

    public string $fieldName = '';

    public string $label = '';

    public mixed $value = null;

    public bool $required = false;

    public bool $readonly = false;

    public mixed $modelId = null;

    public string $displayTitle = '';

    public string $listComponent = '';

    public ?string $detailComponent = null;

    public ?string $legacySelectEvent = null;

    public function mount(
        string $relationType,
        string $fieldName,
        string $label = '',
        mixed $value = null,
        bool $required = false,
        bool $readonly = false,
        mixed $modelId = null,
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
        $this->modelId = $modelId;
        $this->listComponent = $definition->listComponent;
        $this->detailComponent = $definition->getDetailComponent();
        $this->legacySelectEvent = $definition->getSelectEvent();

        $this->resolveDisplayTitle();
    }

    #[On('noerdRelationSelected')]
    public function relationSelected(mixed $value, ?string $context = null): void
    {
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
        $this->value = null;
        $this->displayTitle = '';
        $this->syncParentState();
    }

    public function openDetail(): void
    {
        if (! $this->value || ! $this->detailComponent) {
            return;
        }

        $this->dispatch(
            event: 'noerdModal',
            modalComponent: $this->detailComponent,
            arguments: ['modelId' => $this->value],
        );
    }

    private function resolveDisplayTitle(): void
    {
        $definition = app(RelationFieldRegistry::class)->resolve($this->relationType);

        $this->displayTitle = $definition?->resolveTitleForValue($this->value) ?? '';
    }

    private function syncParentState(): void
    {
        $this->dispatch('setFieldValue',
            field: $this->fieldName,
            value: $this->value,
            relationTitle: $this->displayTitle,
        );
    }
}; ?>

<div>
    <x-noerd::input-label for="{{ $fieldName }}" :value="__($label)" :required="$required"/>
    <div class="flex">
        <input
            class="w-full cursor-pointer border rounded-lg block read-only:shadow-none appearance-none text-base sm:text-sm py-2 h-8 leading-[1.375rem] ps-3 pe-3 bg-white text-zinc-700 read-only:text-zinc-500 placeholder-zinc-400 read-only:placeholder-zinc-400/70 shadow-xs border-zinc-200 border-b-zinc-300/80 read-only:border-b-zinc-200 focus:outline-none focus:ring-2 focus:ring-brand-border focus:ring-offset-2"
            type="text"
            readonly
            id="{{ $fieldName }}"
            value="{{ $displayTitle }}"
            @click="@if($displayTitle) $wire.openDetail() @elseif(! $readonly) $modal('{{ $listComponent }}', {id: {{ $modelId ?: 'null' }}, context: '{{ $fieldName }}', listActionMethod: 'selectAction'}) @endif"
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

        @if(! $readonly)
            <x-noerd::button
                @click="$modal('{{ $listComponent }}', {id: {{ $modelId ?: 'null' }}, context: '{{ $fieldName }}', listActionMethod: 'selectAction'})"
                class="h-8 rounded !mt-0 !ml-1"
                type="button"
            >
                <x-noerd::icons.magnifying-glass></x-noerd::icons.magnifying-glass>
            </x-noerd::button>
        @endif
    </div>
</div>
