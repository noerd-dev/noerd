@props([
    'showDelete' => false,
    'showSave' => true,
    'deleteMessage' => null,
])

@php
    // Object permissions: hide Save/Delete for every detail at once when the
    // current user may not save/delete the object (see the noerd.object-* gates /
    // AccessHelper). canSaveObject() picks create (new record) vs. write (update).
    $barComponent = $__livewire ?? null;
    $showSave = $showSave
        && (! $barComponent || ! method_exists($barComponent, 'canSaveObject') || $barComponent->canSaveObject());
    $showDelete = $showDelete
        && (! $barComponent || ! method_exists($barComponent, 'canDeleteObject') || $barComponent->canDeleteObject());
@endphp

<div {{ $attributes->merge(['class' => 'ml-auto']) }} x-data="{ showButtons: false }">
    <div class="ml-auto flex gap-2">
        <x-noerd::success-indicator class="mt-2 mr-2" />

        @if ($deleteMessage)
            <x-noerd::button
                variant="danger"
                wire:key="delete-confirm"
                wire:click="delete"
                x-show="showButtons"
                wire:confirm="{{ $deleteMessage }}"
            >
                {{ __('Delete') }}
            </x-noerd::button>
        @else
            <x-noerd::button
                variant="danger"
                wire:key="delete-confirm"
                wire:click="delete"
                x-show="showButtons"
            >
                {{ __('Delete') }}
            </x-noerd::button>
        @endif

        <x-noerd::button variant="ghost" x-show="showButtons" @click="showButtons = false">
            {{ __('Cancel') }}
        </x-noerd::button>

        @if ($showDelete)
            <x-noerd::button
                variant="danger"
                wire:key="delete-open"
                x-show="! showButtons"
                @click="showButtons = true"
            >
                {{ __('Delete') }}
            </x-noerd::button>
        @endif

        @if ($showSave)
            <x-noerd::button wireTarget="store" icon="check-circle" wire:click="store">
                {{ __('Save') }}
            </x-noerd::button>
        @endif
    </div>
</div>
