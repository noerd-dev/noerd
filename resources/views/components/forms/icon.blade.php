@php
    $name = $field['name'] ?? '';
    $label = $field['label'] ?? '';
    $required = $field['required'] ?? false;
    $readonly = $field['readonly'] ?? false;
    $current = $iconValue ?? null;
@endphp

<div>
    <x-noerd::input-label for="{{ $name }}" :value="__($label)" :required="$required" />
    <div class="flex items-center gap-2">
        <button
            type="button"
            @if (! $readonly) @click="$modal('noerd::icon-picker', {context: '{{ $name }}'})" @endif
            class="flex items-center gap-2 border rounded-lg h-8 px-3 bg-white text-zinc-700 shadow-xs border-zinc-200 border-b-zinc-300/80 focus:outline-none focus:ring-2 focus:ring-brand-border focus:ring-offset-2 @if(! $readonly) cursor-pointer hover:bg-gray-50 @endif"
        >
            @if ($current)
                <x-icon :name="$current" class="h-5 w-5 text-zinc-700" />
                <span class="text-sm text-zinc-700">{{ \Illuminate\Support\Str::headline($current) }}</span>
            @else
                <span class="text-sm text-zinc-400">{{ __('Select icon') }}</span>
            @endif
        </button>

        @if ($current && ! $readonly)
            <button
                type="button"
                @click="$wire.set('{{ $name }}', null)"
                class="inline-flex h-8 items-center px-2 text-zinc-400 hover:text-zinc-600"
            >
                <x-noerd::icons.x-mark class="h-5 w-5" />
            </button>
        @endif
    </div>
    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2" />
</div>
