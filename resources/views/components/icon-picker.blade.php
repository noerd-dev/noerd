<?php

use Livewire\Component;
use Noerd\Helpers\IconHelper;

new class extends Component {
    public string $context = '';

    public function mount(string $context = ''): void
    {
        $this->context = $context;
    }

    public function selectIcon(string $name): void
    {
        $this->dispatch('setFieldValue', field: $this->context, value: $name);
        $this->dispatch('closeTopModal');
    }

    public function with(): array
    {
        return [
            'icons' => IconHelper::heroicons(),
        ];
    }
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Select Icon') }}</x-noerd::modal-title>
    </x-slot:header>

    <div x-data="{ search: '' }" class="py-6">
        <x-noerd::text-input
            type="text"
            x-model="search"
            autofocus
            placeholder="{{ __('Search icons') }}"
            class="mt-0"
        />

        <div class="grid grid-cols-4 sm:grid-cols-6 gap-2 mt-4">
            @foreach($icons as $name)
                <button
                    type="button"
                    wire:key="icon-{{ $name }}"
                    title="{{ $name }}"
                    x-show="search === '' || '{{ $name }}'.includes(search.toLowerCase().replaceAll(' ', '-'))"
                    @click="$wire.selectIcon('{{ $name }}')"
                    class="flex flex-col items-center justify-center gap-1 p-2 rounded-lg border border-transparent hover:bg-gray-50 hover:border-gray-300 cursor-pointer"
                >
                    <x-icon name="{{ $name }}" class="w-6 h-6 text-gray-700"/>
                    <span class="w-full truncate text-center text-[10px] leading-tight text-gray-500">{{ $name }}</span>
                </button>
            @endforeach
        </div>
    </div>
</x-noerd::page>
