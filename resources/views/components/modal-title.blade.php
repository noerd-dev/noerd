@php
    // Detail headers get the module-contributed header actions (HeaderActionsRegistry)
    // injected here — modal-title is the one generic component every *-detail header
    // goes through. Lists inject theirs in list-header (their component ends in -list),
    // and the slim quick-create dialogs are no place for admin tooling.
    $headerActionComponent = isset($__livewire) && str_ends_with($__livewire->getName(), '-detail')
        && ! ($__livewire->quickCreate ?? false)
        ? $__livewire->getName()
        : null;
    $headerActionViews = $headerActionComponent !== null
        ? app(\Noerd\Services\HeaderActionsRegistry::class)->all()
        : [];
@endphp
<div class="lg:flex py-6 px-6 border-b border-gray-300">
    <x-noerd::title>
        {{$slot}}
        @if(isset($actions) || $headerActionViews !== [])
            <div class="ml-auto flex items-center gap-4 shrink-0"
                 :class="isModal ? modalControlsClass : ''">
                @foreach($headerActionViews as $headerActionView)
                    @include($headerActionView, [
                        'component' => $headerActionComponent,
                        'viewType' => 'detail',
                        'actionsRendered' => true,
                    ])
                @endforeach
                {{ $actions ?? '' }}
            </div>
        @endif
    </x-noerd::title>
</div>
