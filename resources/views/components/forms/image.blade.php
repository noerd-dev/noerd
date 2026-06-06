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

    @php
        $mediaAvailable = app(\Noerd\Contracts\MediaResolverContract::class)->isAvailable();
    @endphp

    @if($previewUrl)
        <div class="group relative mt-1 h-[150px] w-[150px] overflow-hidden rounded-lg border border-zinc-200 bg-zinc-50">
            <div class="absolute inset-0" style="background: url('{{ $previewUrl }}') center / cover;"></div>

            <div class="absolute top-2 right-2 flex gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                @if($mediaAvailable)
                    <button type="button"
                            wire:click="openSelectMediaModal('{{ $resolvedName }}')"
                            title="{{ __('Choose image from media') }}"
                            aria-label="{{ __('Choose image from media') }}"
                            class="flex h-7 w-7 items-center justify-center rounded-full bg-white/90 shadow transition-colors hover:bg-white">
                        <x-dynamic-component component="heroicons::outline.photo" class="h-4 w-4 text-zinc-600"/>
                    </button>
                @endif
                <button type="button"
                        wire:click="deleteImage('{{ $resolvedName }}')"
                        wire:confirm="{{ __('Remove this image? The original file stays in the media library.') }}"
                        title="{{ __('Remove this image') }}"
                        aria-label="{{ __('Remove this image') }}"
                        class="flex h-7 w-7 items-center justify-center rounded-full bg-white/90 shadow transition-colors hover:bg-white">
                    <span class="text-lg leading-none text-red-600">×</span>
                </button>
            </div>
        </div>
    @endif

    @if(! $previewUrl || ! $mediaAvailable)
        <div class="mt-2 flex gap-2">
            @if($mediaAvailable)
                <x-noerd::button variant="secondary"
                    type="button"
                    wire:click="openSelectMediaModal('{{ $resolvedName }}')"
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
    @endif

    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2"/>
</div>
