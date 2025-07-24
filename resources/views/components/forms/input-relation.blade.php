<div>
    <x-noerd::input-label for="{{$field['name']}}" :value="$field['label']"/>
    <div class="flex">
        <input
            {{ ($field['disabled'] ?? false) ? 'disabled' : '' }} {{ ($field['startField'] ?? false) ? "id=component-start-field" : '' }}
            class="w-full border rounded-lg block disabled:shadow-none dark:shadow-none appearance-none text-base sm:text-sm py-2 h-10 leading-[1.375rem] ps-3 pe-3 bg-white dark:bg-white/10 dark:disabled:bg-white/[7%] text-zinc-700 disabled:text-zinc-500 placeholder-zinc-400 disabled:placeholder-zinc-400/70 dark:text-zinc-300 dark:disabled:text-zinc-400 dark:placeholder-zinc-400 dark:disabled:placeholder-zinc-500 shadow-xs border-zinc-200 border-b-zinc-300/80 disabled:border-b-zinc-200 dark:border-white/10 dark:disabled:border-white/5"
            type="text"
            data-flux-control
            id="{{$field['name'] ?? $name ?? ''}}"
            name="{{$field['name'] ?? $name ?? ''}}"
            @if((isset($field['live']) || isset($live)) && $field['live'] ?? $live)
                wire:model.live.debounce="{{$field['relationField']}}"
            @else
                wire:model="{{$field['relationField']}}"
            @endif
        >

        <x-noerd::buttons.primary
            wire:click="$dispatch('noerdModal', {component: '{{$field['modalComponent'] ?? null}}', arguments: {id: {{$modelId ?? 0}}}})"
            class="!h-[40px] rounded !mt-0 !ml-1"
            type="button">
            <x-noerd::icons.magnifying-glass></x-noerd::icons.magnifying-glass>
        </x-noerd::buttons.primary>
    </div>
    <x-noerd::input-error :messages="$errors->get($field['name'])" class="mt-2"/>
</div>
