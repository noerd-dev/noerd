

<div class="">
    <nav role="navigation" aria-label="Pagination Navigation" class="flex items-center justify-between py-3 !h-[44px] my-auto">
        {{-- Info text + per-page dropdown left --}}
        <div class="hidden sm:flex sm:items-center sm:gap-4">
            <p class="text-sm text-gray-700">
                <span>{!! __('Showing') !!}</span>
                <span class="font-medium">{{ $paginator->firstItem() }}</span>
                <span>{!! __('to') !!}</span>
                <span class="font-medium">{{ $paginator->lastItem() }}</span>
                <span>{!! __('of') !!}</span>
                <span class="font-medium">{{ $paginator->total() }}</span>
                <span>{!! __('results') !!}</span>
            </p>
            <select wire:model.live="perPage" class="text-sm text-gray-700 border-gray-300 rounded-md py-1 pl-2 pr-7 focus:border-brand-primary focus:ring-brand-primary cursor-pointer">
                @foreach([10, 50, 100, 250, 500, 1000] as $size)
                    <option value="{{ $size }}">{{ $size }}</option>
                @endforeach
            </select>
        </div>

        {{-- Previous + Next right --}}
        @if ($paginator->hasPages())
            <div class="flex items-center gap-6">
                @if ($paginator->onFirstPage())
                    <span class="inline-flex items-center border-t-2 border-transparent text-sm font-medium text-gray-300 cursor-default">
                        <svg class="mr-3 size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M17 10a.75.75 0 0 1-.75.75H5.612l4.158 3.96a.75.75 0 1 1-1.04 1.08l-5.5-5.25a.75.75 0 0 1 0-1.08l5.5-5.25a.75.75 0 1 1 1.04 1.08L5.612 9.25H16.25A.75.75 0 0 1 17 10Z" clip-rule="evenodd" />
                        </svg>
                        {!! __('Previous') !!}
                    </span>
                @else
                    <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" dusk="previousPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}" class="inline-flex items-center border-t-2 border-transparent text-sm font-medium text-gray-500 cursor-pointer hover:text-gray-700 transition">
                        <svg class="mr-3 size-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M17 10a.75.75 0 0 1-.75.75H5.612l4.158 3.96a.75.75 0 1 1-1.04 1.08l-5.5-5.25a.75.75 0 0 1 0-1.08l5.5-5.25a.75.75 0 1 1 1.04 1.08L5.612 9.25H16.25A.75.75 0 0 1 17 10Z" clip-rule="evenodd" />
                        </svg>
                        {!! __('Previous') !!}
                    </button>
                @endif

                @if ($paginator->hasMorePages())
                    <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')"  wire:loading.attr="disabled" dusk="nextPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}" class="inline-flex items-center border-t-2 border-transparent text-sm font-medium text-gray-500 cursor-pointer hover:text-gray-700 transition">
                        {!! __('Next') !!}
                        <svg class="ml-3 size-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638l-4.158-3.96a.75.75 0 0 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 0 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" />
                        </svg>
                    </button>
                @else
                    <span class="inline-flex items-center border-t-2 border-transparent text-sm font-medium text-gray-300 cursor-default">
                        {!! __('Next') !!}
                        <svg class="ml-3 size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638l-4.158-3.96a.75.75 0 0 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 0 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" />
                        </svg>
                    </span>
                @endif
            </div>
        @endif
    </nav>
</div>
