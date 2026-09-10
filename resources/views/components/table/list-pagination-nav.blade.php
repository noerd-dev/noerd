{{--
    Pagination summary ("1-50 of 150") plus icon-only previous/next buttons. ONE
    partial for both places it appears — the right group of the list's filter row
    and the pagination footer — so the two can never drift apart. It is rendered
    twice per page with byte-identical markup: no wire:key, no placement suffix.

    Expects: $paginator (a LengthAwarePaginator). Never reads $this — the footer
    copy is rendered through $rows->links(), where the component is not in scope.

    The buttons are labelled with aria-label, never with an sr-only span: sr-only
    is position:absolute, and inside a button without its own positioning the
    span escapes the body's scroll container and stretches the document.
--}}
@php
    $pageName = $paginator->getPageName();
@endphp

<div class="flex shrink-0 items-center gap-2">
    <span class="hidden whitespace-nowrap text-sm font-normal text-gray-700 tabular-nums sm:inline">
        {{ __(':from-:to of :total', [
            'from' => $paginator->firstItem() ?? 0,
            'to' => $paginator->lastItem() ?? 0,
            'total' => $paginator->total(),
        ]) }}
    </span>
    <x-noerd::button
        variant="control"
        type="button"
        icon="heroicons::mini.solid.chevron-left"
        wire:click="previousPage('{{ $pageName }}')"
        wire:loading.attr="disabled"
        :disabled="$paginator->onFirstPage()"
        :title="__('Previous')"
        :aria-label="__('Previous')"
    />
    <x-noerd::button
        variant="control"
        type="button"
        icon="heroicons::mini.solid.chevron-right"
        wire:click="nextPage('{{ $pageName }}')"
        wire:loading.attr="disabled"
        :disabled="! $paginator->hasMorePages()"
        :title="__('Next')"
        :aria-label="__('Next')"
    />
</div>
