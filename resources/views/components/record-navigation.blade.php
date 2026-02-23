@props([
    'recordNavInfo' => ['available' => false],
])

<div class="hidden sm:flex flex-col gap-1 absolute -left-14 top-5 z-10">
    <button
        wire:click="navigateRecord('prev')"
        @if(!$recordNavInfo['hasPrev']) disabled @endif
        @class([
            'rounded-lg p-1.5 transition-colors focus:outline-hidden',
            'text-white/70 hover:text-white hover:bg-white/20' => $recordNavInfo['hasPrev'],
            'text-white/20 cursor-default' => !$recordNavInfo['hasPrev'],
        ])
        title="{{ __('noerd_nav_previous_record') }}"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
        </svg>
    </button>
    <button
        wire:click="navigateRecord('next')"
        @if(!$recordNavInfo['hasNext']) disabled @endif
        @class([
            'rounded-lg p-1.5 transition-colors focus:outline-hidden',
            'text-white/70 hover:text-white hover:bg-white/20' => $recordNavInfo['hasNext'],
            'text-white/20 cursor-default' => !$recordNavInfo['hasNext'],
        ])
        title="{{ __('noerd_nav_next_record') }}"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
        </svg>
    </button>
</div>
