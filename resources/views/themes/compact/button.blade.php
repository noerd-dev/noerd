{{-- Compact variant of the button field: aligned with the label-left rows of its siblings. --}}
@php
    $name = $field['name'] ?? $name ?? '';
    $label = $field['label'] ?? $label ?? '';
    $readonly = $field['readonly'] ?? false;
@endphp

<div class="flex h-full items-center gap-2">
    <span class="w-36 shrink-0"></span>
    <div class="min-w-0 flex-1">
        @if ($readonly)
            <x-noerd::button theme="compact" disabled type="button"> {{ __($label) }} </x-noerd::button>
        @else
            <x-noerd::button theme="compact" wire:click="{{ $name }}" type="button"> {{ __($label) }} </x-noerd::button>
        @endif
    </div>
</div>
