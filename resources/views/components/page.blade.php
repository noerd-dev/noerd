@php
    $hasCurrentTab = property_exists($__livewire ?? new stdClass, 'currentTab');
    $disableModal = $disableModal ?? (($__livewire ?? null)?->disableModal ?? false);

    $shortcuts = [];
    if (method_exists($__livewire ?? new stdClass, 'store')) {
        $shortcuts['save'] = config('noerd.keyboard_shortcuts.save', 'ctrl+enter');
    }
    if (method_exists($__livewire ?? new stdClass, 'delete')) {
        $shortcuts['delete'] = config('noerd.keyboard_shortcuts.delete', 'ctrl+backspace');
    }
@endphp
<div x-data="noerdPage({
        currentTab: @if($hasCurrentTab)@entangle('currentTab')@else 1 @endif,
        shortcuts: @js($shortcuts),
        deleteMessage: @js(__('Are you sure you want to delete this entry?')),
    })"
     class="flex flex-col"
     @if($disableModal)
         style="margin-left: -32px; margin-right: -32px"
     @else
     :class="isModal ? '-m-6 -mt-12 flex flex-col max-h-[calc(100dvh-64px)] transition-[max-height] duration-200 ease-out ' + (isRight ? 'sm:max-h-[calc(100dvh)]' : (modalFullscreen ? 'sm:max-h-[calc(100dvh-3.5rem)]' : 'sm:max-h-[calc(100dvh-7rem)]')) : 'h-[calc(100dvh_-_2.9375rem_-_var(--banner-height,0px)_-_var(--impersonation-banner-height,0px)_-_var(--environment-banner-height,0px))]'"
    @endif
>
    {{$header ?? ''}}
    {{$table ?? ''}}

    <div class="flex-1 min-h-0 px-6 overflow-y-auto{{ $hasCurrentTab ? ' flex flex-col' : '' }}">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="flex w-full border-t border-gray-300 py-4 px-8 z-50 pb-[max(1rem,env(safe-area-inset-bottom))]">
            {{$footer}}
        </div>
    @endif
</div>
