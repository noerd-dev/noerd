{{-- The list's pagination footer: the rows-per-page select (footer only) and the
     same summary + previous/next buttons the filter row shows at the top. --}}
<div>
    <nav
        role="navigation"
        aria-label="{{ __('Pagination Navigation') }}"
        class="flex items-center justify-between py-3"
    >
        {{-- The options never exceed NoerdList::MAX_PER_PAGE: clampPerPage()
             would silently reject anything above it, leaving the select without
             a matching option. --}}
        <label class="flex items-center gap-2 text-sm text-gray-700">
            <span class="whitespace-nowrap">{{ __('Rows per page') }}</span>
            <select
                wire:model.live="perPage"
                class="focus:border-brand-primary focus:ring-brand-primary h-8 cursor-pointer rounded-md border-gray-300 py-1 pr-7 pl-2 text-sm text-gray-700"
            >
                @foreach ([10, 25, 50, 100, 200] as $size)
                    <option value="{{ $size }}">{{ $size }}</option>
                @endforeach
            </select>
        </label>

        <div class="ml-auto">
            @include('noerd::components.table.list-pagination-nav', ['paginator' => $paginator])
        </div>
    </nav>
</div>
