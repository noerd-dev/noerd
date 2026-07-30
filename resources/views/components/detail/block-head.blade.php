<div>
    <div class="pb-2 text-sm leading-6 font-semibold text-gray-900">{{ $title }}</div>
    @if ($description)
        <p class="mt-1 text-sm text-gray-500">{{ $description ?? '' }}</p>
    @endif
</div>
