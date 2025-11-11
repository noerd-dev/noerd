@php
    $collections = \Noerd\Cms\Models\Collection::where('tenant_id', auth()->user()->selected_tenant_id)->orderBy('name')->get();
@endphp

<div>
    <x-noerd::input-label for="{{$field['name']}}" :value="__($field['label'])"/>
    <div class="flex">
        <select
            @if(isset($field['live']) && $field['live'])
                wire:model.live.debounce="{{$field['name']}}"
            @else
                wire:model="{{$field['name']}}"
            @endif
            {{ ($field['disabled'] ?? false) ? 'disabled' : '' }}
            class="w-full border rounded-lg block disabled:shadow-none dark:shadow-none appearance-none text-base sm:text-sm py-2 h-10 leading-[1.375rem] ps-3 pe-3 bg-white dark:bg-white/10 dark:disabled:bg-white/[7%] text-zinc-700 disabled:text-zinc-500 placeholder-zinc-400 disabled:placeholder-zinc-400/70 dark:text-zinc-300 dark:disabled:text-zinc-400 dark:placeholder-zinc-400 dark:disabled:placeholder-zinc-500 shadow-xs border-zinc-200 border-b-zinc-300/80 disabled:border-b-zinc-200 dark:border-white/10 dark:disabled:border-white/5"
            data-flux-control
            id="{{$field['name']}}">
            <option value="">{{ __('Bitte wählen...') }}</option>
            @foreach($collections as $collection)
                <option value="{{ $collection->id }}">{{ $collection->name }}</option>
            @endforeach
        </select>

        <x-noerd::buttons.primary
            x-data="{ collectionKey: $wire.entangle('{{$field['name']}}') }"
            @click="$dispatch('noerdModal', {component: 'collection-entries-list', arguments: {collectionKey: collectionKey, context: '{{$field['name'] ?? null}}'}})"
            class="!h-[40px] rounded !mt-0 !ml-1"
            type="button">
            <x-noerd::icons.magnifying-glass></x-noerd::icons.magnifying-glass>
        </x-noerd::buttons.primary>
    </div>
    <x-noerd::input-error :messages="$errors->get($field['name'])" class="mt-2"/>
</div>
