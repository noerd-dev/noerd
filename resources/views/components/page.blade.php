@php $isFullscreen = session('modal_fullscreen', false); @endphp
<div x-data="{currentTab: @entangle('currentTab')}"
     class="flex flex-col"
     @if($disableModal ?? false)
         style="margin-left: -32px; margin-right: -32px"
     @else
     :class="isModal ? 'flex flex-col max-h-[calc(100dvh-64px)] {{ $isFullscreen ? 'sm:max-h-[calc(100dvh-3.5rem)]' : 'sm:max-h-[calc(100vh-112px)]' }}' : 'h-full'"
    @endif
>
    {{$header ?? ''}}
    {{$table ?? ''}}

    <div class="flex-1 p-6 overflow-y-auto" @if($disableModal ?? false) class="!p-0" @else class="p-6"
         @endif :class="isModal ? 'flex-1 p-6 pt-0! overflow-y-auto' : 'h-full pt-0!'">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="flex w-full border-t border-gray-300 py-4 px-8 z-50 pb-[max(1rem,env(safe-area-inset-bottom))]">
            {{$footer}}
        </div>
    @endif
</div>
