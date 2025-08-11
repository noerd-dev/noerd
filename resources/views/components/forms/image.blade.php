<div {{ isset($attributes) ?$attributes->merge(['class' => '']) : '' }}>

    <x-noerd::input-label for="{{$field['name'] ?? $name}}" :value="$field['label'] ?? $label"/>

    @php
        $valueKey = $field['name'] ?? $name ?? '';
        $rawValue = isset($model) && isset($model[$valueKey]) ? $model[$valueKey] : null;
        $previewUrl = null;
        if (is_numeric($rawValue)) {
            try {
                $media = \Nywerk\Media\Models\Media::find((int) $rawValue);
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
            <div style="height: 150px; width: 150px; background: url('{{$previewUrl}}') 0% 0% / cover;">
                <button wire:confirm="Bild wirklich löschen?"
                        wire:click="deleteImage('{{$field['name']}}')"
                        type="button"
                        class=" top-5 right-0 inline-flex uppercase items-center rounded !bg-red-400 p-1.5 m-2 text-sm font-medium text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    X
                </button>
            </div>
        </div>
    @endif

    <div class="mt-2 flex gap-2">
        <x-noerd::buttons.secondary type="button"
                                    wire:click="openSelectMediaModal('{{$field['name'] ?? $name}}')">
            {{ __('Bild aus Medien wählen') }}
        </x-noerd::buttons.secondary>
    </div>

    <x-noerd::input-error :messages="$errors->get($field['name'] ?? $name)" class="mt-2"/>
</div>
