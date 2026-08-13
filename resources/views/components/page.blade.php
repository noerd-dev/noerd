@php
    $hasCurrentTab = property_exists($__livewire ?? new stdClass(), 'currentTab');
    $disableModal ??= (($__livewire ?? null)?->disableModal ?? false);

    // A detail rendered inside a hosting page component (e.g. account-page embeds
    // account-detail) skips the page chrome entirely: no header/footer slots, no
    // scroll wrapper, no keyboard shortcuts — the hosting page owns all of that.
    $embedded ??= (($__livewire ?? null)?->embedded ?? false);

    // Object permissions: denied abilities lose their keyboard shortcut too —
    // hiding the buttons alone would leave ctrl+enter / ctrl+backspace live.
    $pageComponent = $__livewire ?? null;
    $pageObjectReadBlocked = $pageComponent?->objectReadBlocked ?? false;
    $canWriteObject = ! $pageComponent
        || ! method_exists($pageComponent, 'canWriteObject')
        || $pageComponent->canWriteObject();
    $canDeleteObject = ! $pageComponent
        || ! method_exists($pageComponent, 'canDeleteObject')
        || $pageComponent->canDeleteObject();

    $shortcuts = [];
    if (method_exists($__livewire ?? new stdClass(), 'store') && $canWriteObject && ! $pageObjectReadBlocked) {
        $shortcuts['save'] = config('noerd.keyboard_shortcuts.save', 'ctrl+enter');
    }
    if (method_exists($__livewire ?? new stdClass(), 'delete') && $canDeleteObject && ! $pageObjectReadBlocked) {
        $shortcuts['delete'] = config('noerd.keyboard_shortcuts.delete', 'ctrl+backspace');
    }
@endphp
{{-- The root element stays unconditional — Livewire derives the component root
     from the first rendered element, so only attributes and children may branch. --}}
<div
    @unless ($embedded)
        x-data="noerdPage({
            currentTab: @if($hasCurrentTab)@entangle('currentTab')@else 1 @endif,
            shortcuts: @js($shortcuts),
            deleteMessage: @js(__('Are you sure you want to delete this entry?')),
        })"
        class="flex flex-col"
        @if ($disableModal)
            style="margin-left: -32px; margin-right: -32px"
        @else
            :class="isModal
                ? '-m-6 -mt-12 flex flex-col max-h-[calc(100dvh-64px)] transition-[min-height,max-height] duration-200 ease-out ' +
                  (isRight
                      ? 'sm:max-h-[calc(100dvh)]'
                      : modalFullscreen
                        ? 'sm:min-h-[100dvh] sm:max-h-[100dvh]'
                        : 'sm:min-h-0 sm:max-h-[calc(100dvh-7rem)]')
                : 'h-[calc(100dvh_-_2.9375rem_-_var(--banner-height,0px)_-_var(--impersonation-banner-height,0px)_-_var(--environment-banner-height,0px))]'"
        @endif
    @endunless
>
    @if ($embedded)
        {{ $slot }}
    @elseif ($pageObjectReadBlocked)
        {{-- Read-denied object: keep the header chrome (modal close button) but
             replace the form body with the friendly denied state — no footer. --}}
        {{ $header ?? '' }}

        <div class="flex min-h-0 flex-1 flex-col overflow-y-auto px-6">
            @include('noerd::components.object-access-denied')
        </div>
    @else
        {{ $header ?? '' }}
        {{ $table ?? '' }}

        <div class="flex-1 min-h-0 px-6 overflow-y-auto{{ $hasCurrentTab ? ' flex flex-col' : '' }}">{{ $slot }}</div>

        @isset($footer)
            <div class="z-50 flex w-full border-t border-gray-300 px-8 py-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
                {{ $footer }}
            </div>
        @endisset
    @endif
</div>
