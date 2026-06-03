<?php

use Livewire\Attributes\On;
use Livewire\Component;
use Noerd\Facades\Noerd;
use Noerd\Services\RelationFieldRegistry;

/**
 * Compact variant of noerd-relation-field: identical behaviour, but the label sits
 * to the LEFT of the relation control. Resolved automatically by the detail block
 * when a field is rendered in compact mode.
 */
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

        Noerd::modal($this->detailComponent, ['modelId' => $this->value]);
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

<div class="flex items-center gap-2">
    <x-noerd::input-label for="{{ $fieldName }}" :value="__($label)" :required="$required" :title="__($label)" class="!pb-0 w-36 shrink-0 truncate"/>
    <div class="flex-1 min-w-0">
        <div class="flex">
            <input
                class="w-full cursor-pointer border border-zinc-200 rounded-sm block appearance-none text-base sm:text-sm py-1 h-7 ps-2 pe-2 bg-white text-zinc-700 read-only:text-zinc-500 placeholder-zinc-400 read-only:placeholder-zinc-400/70 focus:outline-none focus:ring-1 focus:ring-brand-border"
                type="text"
                readonly
                id="{{ $fieldName }}"
                value="{{ $displayTitle }}"
                @click="@if($displayTitle) $wire.openDetail() @elseif(! $readonly) $modal('{{ $listComponent }}', {id: {{ $modelId ?: 'null' }}, context: '{{ $fieldName }}', listActionMethod: 'selectAction'}) @endif"
            >

            @if($displayTitle && ! $readonly)
                <button
                    wire:click="clear"
                    class="h-7 inline-flex items-center px-2 !mt-0 !ml-1 text-zinc-400 hover:text-zinc-600"
                    type="button"
                >
                    <x-noerd::icons.x-mark class="w-5 h-5"></x-noerd::icons.x-mark>
                </button>
            @endif

            @if(! $readonly)
                <x-noerd::button
                    @click="$modal('{{ $listComponent }}', {id: {{ $modelId ?: 'null' }}, context: '{{ $fieldName }}', listActionMethod: 'selectAction'})"
                    class="!h-7 !px-2 rounded-sm !mt-0 !ml-1"
                    type="button"
                >
                    <x-noerd::icons.magnifying-glass></x-noerd::icons.magnifying-glass>
                </x-noerd::button>
            @endif
        </div>
    </div>
</div>
