@props([
    'field' => null,
    'name' => '',
    'label' => '',
    'detailData' => null,
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
        $resolvedName = str_replace('detailData.', '', $name);
        $rawValue = null;
        if (isset($detailData) && isset($detailData[$resolvedName])) {
            $rawValue = $detailData[$resolvedName];
        } elseif (isset($this->detailData[$resolvedName])) {
            $rawValue = $this->detailData[$resolvedName];
        }
        $previewUrl = null;
        if (is_numeric($rawValue)) {
            try {
                $previewUrl = app(\Noerd\Contracts\MediaResolverContract::class)->getPreviewUrl((int) $rawValue);
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
                <x-noerd::button variant="danger" :icon="false"
                                 wire:confirm="{{ __('Really delete image?') }}"
                                 wire:click="deleteImage('{{ $name }}')"
                                 class="m-2">
                    X
                </x-noerd::button>
            </div>
        </div>
    @endif

    @php
        $mediaAvailable = app(\Noerd\Contracts\MediaResolverContract::class)->isAvailable();
    @endphp

    <div class="mt-2 flex gap-2">
        @if($mediaAvailable)
            <x-noerd::button variant="secondary"
                type="button"
                wire:click="openSelectMediaModal('{{ $name }}')"
            >
                {{ __('Choose image from media') }}
            </x-noerd::button>
        @else
            <input
                type="file"
                wire:model.live="imageUploads.{{ $resolvedName }}"
                accept="image/*"
                class="w-full border rounded-lg block text-base sm:text-sm py-2 h-10 ps-3 pe-3
                       bg-white text-zinc-700 border-zinc-200
                       file:mr-4 file:py-1 file:px-4 file:rounded file:border-0
                       file:text-sm file:font-medium file:bg-zinc-100 file:text-zinc-700
                       focus:outline-none focus:ring-2 focus:ring-brand-border focus:ring-offset-2"
            >
        @endif
    </div>

    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2"/>
</div>
