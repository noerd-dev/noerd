@props(['disabled' => false, 'startField' => false, 'model' => null, 'id' => null])

<div class="flex">
    <input wire:model="{{$model}}" id="{{$id}}"  disabled style="border: 2px solid #ccc;" {{ $disabled ? 'disabled' : '' }} {{ $startField ? "id=component-start-field" : '' }} {!! $attributes->merge(['class' => 'disabled:opacity-50 border-gray-300 text-sm focus:ring-gray-300 shadow-xs py-1']) !!}>

    <button
        wire:click="$dispatch('noerdModal', {component: 'firewood::livewire.firewood-storage-location-select-modal', arguments: {id: {{$modelId ?? 0}}}})"
        class="h-[32px] mt-[4px] inline-flex uppercase items-center rounded-sm border border-black bg-black px-6 py-1.5 text-sm font-medium text-white hover:bg-gray-800 focus:outline-hidden focus:ring-2 focus:ring-black focus:ring-offset-2"
        type="button">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
             stroke="currentColor" class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
        </svg>
    </button>
</div>
