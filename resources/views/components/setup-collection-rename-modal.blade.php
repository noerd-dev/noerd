<?php

use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Asks whether renamed collection fields should be migrated in the stored
 * entries. Opened by setup-collection-definition-detail; the answer goes back
 * through the `collectionRenameConfirmed` event.
 */
new class extends Component {
    /** @var array<string, string> old field name => new field name */
    #[Locked]
    public array $renames = [];

    public function confirm(): void
    {
        $this->answer(true);
    }

    public function skip(): void
    {
        $this->answer(false);
    }

    private function answer(bool $apply): void
    {
        $this->dispatch('collectionRenameConfirmed', apply: $apply);
        $this->dispatch('closeTopModal');
    }
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Fields were renamed') }}</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="[]" :modelId="null" :showBlock="false">
        <x-slot:tab1>
            <p class="text-sm text-gray-600 mb-4">{{ __('Would you like to update existing entries to use the new field names?') }}</p>
            <ul class="text-sm text-gray-700 space-y-1">
                @foreach($renames as $oldName => $newName)
                    <li class="flex items-center gap-2" wire:key="rename-{{ $oldName }}">
                        <span class="font-mono bg-gray-100 px-1.5 py-0.5 rounded">{{ $oldName }}</span>
                        <x-noerd::icons.chevron-right class="h-4 w-4 text-gray-400 shrink-0" />
                        <span class="font-mono bg-gray-100 px-1.5 py-0.5 rounded">{{ $newName }}</span>
                    </li>
                @endforeach
            </ul>
        </x-slot:tab1>
    </x-noerd::tab-content>

    <x-slot:footer>
        <div class="ml-auto flex items-center gap-2">
            <x-noerd::button variant="secondary" wire:click="skip">{{ __('No, skip') }}</x-noerd::button>
            <x-noerd::button variant="primary" wire:click="confirm">{{ __('Yes, update') }}</x-noerd::button>
        </div>
    </x-slot:footer>
</x-noerd::page>
