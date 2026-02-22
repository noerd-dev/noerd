@props([
    'field' => null,
    'name' => '',
    'label' => '',
    'model' => null,
])

@php
    $name = $field['name'] ?? $name;
    $label = $field['label'] ?? $label;
    // Filter out non-scalar values that leak from parent scope via @include
    $attributes = $attributes->filter(fn($value) => is_scalar($value) || is_null($value));
@endphp

<div {{ $attributes->merge(['class' => '']) }}>
    <x-noerd::input-label for="{{ $name }}" :value="__($label)"/>

    @php
        $rawValue = isset($model) && isset($model[$name]) ? $model[$name] : null;
        $previewUrl = null;
        if (is_numeric($rawValue)) {
            try {
                $media = \Noerd\Media\Models\Media::find((int) $rawValue);
                if ($media) {
                    $previewUrl = \Illuminate\Support\Facades\Storage::disk($media->disk)->url($media->thumbnail ?? $media->path);
                }
            } catch (Throwable $e) {
                $previewUrl = null;
            }
        } elseif (is_string($rawValue)) {
            $previewUrl = $rawValue;
        }
    @endphp

    @if($previewUrl)
        <div class="relative mr-4 mb-4">
            <div style="height: 150px; width: 150px; background: url('{{ $previewUrl }}') 0% 0% / cover;">
                <button
                    wire:confirm="{{ __('Really delete image?') }}"
                    wire:click="deleteImage('{{ $name }}')"
                    type="button"
                    class="top-5 right-0 inline-flex uppercase items-center rounded !bg-red-400 p-1.5 m-2 text-sm font-medium text-white focus:outline-none focus:ring-2 focus:ring-brand-border focus:ring-offset-2"
                >
                    X
                </button>
            </div>
        </div>
    @endif

    <div class="mt-2 flex gap-2">
        <x-noerd::buttons.secondary
            type="button"
            wire:click="openSelectMediaModal('{{ $name }}')"
        >
            {{ __('Choose image from media') }}
        </x-noerd::buttons.secondary>
    </div>

    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2"/>
</div>
