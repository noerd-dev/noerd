<div class="lg:flex py-6 px-6 border-b border-gray-300">
    <x-noerd::title>
        {{$slot}}
        @isset($actions)
            <div class="ml-auto flex items-center gap-4 shrink-0"
                 :class="isModal ? modalControlsClass : ''">
                {{ $actions }}
            </div>
        @endisset
    </x-noerd::title>
</div>
