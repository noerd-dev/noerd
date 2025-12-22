<div class="mt-auto h-full flex">
    <div class="relative flex items-start my-auto">
        <div class="flex h-6 items-center">
            <input @if($field['disabled'] ?? false) disabled @endif
            @if($field['live'] ?? false)
                wire:model.live.debounce="{{$field['name']}}"
                   @else
                       wire:model="{{$field['name']}}"
                   @endif
                   id="{{$field['name']}}"
                   type="checkbox"
                   class="h-4 w-4 rounded-sm border border-gray-300 text-black focus:ring-black">
        </div>
        <div class="ml-3 text-sm leading-6">
            <label for="{{$field['name']}}" class="font-medium text-gray-900">{{__($field['label'])}}</label>
        </div>
    </div>

    <x-noerd::input-error :messages="$errors->get($field['name'])" class="mt-2"/>
</div>
