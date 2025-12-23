@props([
    'model' => '',
    'id' => '',
])

<x-noerd::select-input
    wire:model="{{ $model }}"
    :id="$id"
    {{ $attributes }}
>
    {{ $slot }}
</x-noerd::select-input>
