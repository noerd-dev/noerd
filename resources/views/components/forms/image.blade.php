<div {{ isset($attributes) ?$attributes->merge(['class' => '']) : '' }}>

    <x-noerd::input-label for="{{$field['name'] ?? $name}}" :value="$field['label'] ?? $label"/>

    @if(isset($model) &&  isset($model[$field['name']]))
        <div class="relative mr-4 mb-4">
            <div style="height: 150px; width: 150px; background: url('{{$model[$field['name']]}}') 0% 0% / cover;">
                <button wire:confirm="Bild wirklich löschen?"
                        wire:click="deleteImage('{{$field['name']}}')"
                        type="button"
                        class=" top-5 right-0 inline-flex uppercase items-center rounded !bg-red-400 p-1.5 m-2 text-sm font-medium text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    X
                </button>
            </div>
        </div>
    @endif

    <input
        {{ ($field['disabled'] ?? false) ? 'disabled' : '' }} {{ ($field['startField'] ?? false) ? "id=component-start-field" : '' }}
        class="w-full border rounded-lg block disabled:shadow-none dark:shadow-none appearance-none text-base sm:text-sm py-2 h-10 leading-[1.375rem] ps-3 pe-3 bg-white dark:bg-white/10 dark:disabled:bg-white/[7%] text-zinc-700 disabled:text-zinc-500 placeholder-zinc-400 disabled:placeholder-zinc-400/70 dark:text-zinc-300 dark:disabled:text-zinc-400 dark:placeholder-zinc-400 dark:disabled:placeholder-zinc-500 shadow-xs border-zinc-200 border-b-zinc-300/80 disabled:border-b-zinc-200 dark:border-white/10 dark:disabled:border-white/5"
        type="file"
        data-flux-control
        id="{{$field['name'] ?? $name}}"
        name="{{$field['name'] ?? $name}}"
        @if((isset($field['live']) || isset($live)) && $field['live'] ?? $live)
            wire:model.live.debounce="images.{{$field['name'] ?? $name}}"
        @else
            wire:model="images.{{$field['name'] ?? $name}}"
        @endif
    >

    <div class="mt-2">
        <x-noerd::buttons.secondary type="button"
                                    wire:click="openSelectMediaModal('{{$field['name'] ?? $name}}')">
            {{ __('Bild aus Medien wählen') }}
        </x-noerd::buttons.secondary>
    </div>

    <x-noerd::input-error :messages="$errors->get($field['name'] ?? $name)" class="mt-2"/>
</div>
