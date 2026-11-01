{{-- The profile area: its navigation (ProfileNavigationRegistry — the core's
     pages plus every page a module registered) left of the page's content.
     Route views wrap it in <x-noerd::app-layout>; a module's full-page Livewire
     component renders it as its root. The navigation sits behind wire:ignore:
     a Livewire update request does not know the page's route, so a re-render
     would lose the active entry. --}}
@php
    $profileNavigation = app(\Noerd\Services\ProfileNavigationRegistry::class)->all();
@endphp

<div {{ $attributes->merge(['class' => 'p-8']) }}>
    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
        <nav class="lg:w-56 lg:shrink-0" aria-label="{{ __('Profile') }}" wire:ignore>
            <ul class="flex gap-1 overflow-x-auto lg:flex-col">
                @foreach ($profileNavigation as $entry)
                    @php($active = request()->routeIs($entry['route']))
                    <li>
                        <a href="{{ route($entry['route']) }}" wire:navigate
                           @if ($active) aria-current="page" @endif
                           @class([
                               'flex items-center gap-2 whitespace-nowrap rounded-md px-3 py-2 text-sm',
                               'bg-brand-primary/10 font-medium text-brand-primary' => $active,
                               'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900' => ! $active,
                           ])>
                            @if ($entry['heroicon'])
                                <x-dynamic-component :component="'heroicons::outline.' . $entry['heroicon']" class="h-4 w-4 shrink-0"/>
                            @endif
                            {{ __($entry['label']) }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="min-w-0 flex-1 space-y-6">
            {{ $slot }}
        </div>
    </div>
</div>
