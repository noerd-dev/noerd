@php
    $name = $field['name'] ?? $name ?? '';
    $label = $field['label'] ?? $label ?? '';
    $readonly = $field['readonly'] ?? false;
@endphp

<div class="mt-auto flex h-full">
    @if ($readonly)
        <x-noerd::button disabled type="button" class="mt-auto h-[40px]!"> {{ __($label) }} </x-noerd::button>
    @else
        <x-noerd::button wire:click="{{ $name }}" type="button" class="mt-auto h-[40px]!"> {{ __($label) }} </x-noerd::button>
    @endif
</div>
