{{-- Thin wire:model wrapper kept for external callers; `x-noerd::select-input` is the primitive. --}}
@props([
    'model' => '',
    'id' => '',
])

<x-noerd::select-input wire:model="{{ $model }}" :id="$id" {{ $attributes }}> {{ $slot }} </x-noerd::select-input>
