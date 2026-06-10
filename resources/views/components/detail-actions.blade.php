@props([
    'layout' => [],
    'modelId' => null,
])

@php
    $actions = collect($layout['actions'] ?? [])
        ->filter(fn (array $action): bool => $modelId || ($action['requiresId'] ?? true) === false)
        ->values();
@endphp

@if ($actions->isNotEmpty())
    <div class="mb-6 first:mt-6 flex flex-wrap items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
        @foreach ($actions as $action)
            <button type="button"
                    wire:click="{{ $action['action'] }}"
                    @if (! empty($action['confirm'])) wire:confirm="{{ __($action['confirm']) }}" @endif
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 shadow-xs hover:bg-gray-100">
                @if (! empty($action['heroicon']))
                    <x-icon name="{{ $action['heroicon'] }}" class="h-4 w-4 text-gray-500"/>
                @endif
                {{ __($action['label']) }}
            </button>
        @endforeach
    </div>
@endif
