<div x-data="{currentTab: 1}"
     class="flex flex-col"
     @if($disableModal ?? false)
         style="margin-left: -32px; margin-right: -32px"
     @else
         :style="isModal ? 'max-height: calc(100vh - 112px)' : ''"
     :class="isModal ? 'flex flex-col' : 'h-full'"
    @endif

>
    {{$header ?? ''}}
    {{$table ?? ''}}

    <div class="flex-1 p-8 overflow-y-auto" @if($disableModal ?? false) class="!p-0" @else class="p-8"
         @endif :class="isModal ? 'flex-1 p-8 pt-0! overflow-y-auto' : 'h-full'">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="flex w-full border-t border-gray-300 py-4 px-8 z-50">
            {{$footer}}
        </div>
    @endif

</div>
