{{-- The entries of one navigation block — in the sidebar and in a sub-navigation.
     $entries: the block's `navigations`, $style: `list` | `buttons`,
     $keyPrefix: keeps the wire:keys of the same entry apart in two panels. --}}
@if(($style ?? 'list') === 'buttons')
    <div class="flex flex-col gap-2">
        @foreach($entries as $navi)
            <livewire:noerd::layout.sidebar-navigation-button wire:key="{{ $keyPrefix }}btn-{{ $navi['title'] }}"
                                                              :navi="$navi"/>
        @endforeach
    </div>
@else
    <ul role="list" class="space-y-1">
        @foreach($entries as $navi)
            <livewire:noerd::layout.sidebar-navigation-element wire:key="{{ $keyPrefix }}nav-{{ $navi['route'] ?? $navi['link'] ?? $navi['title'] }}"
                                                               :navi="$navi"/>
        @endforeach
    </ul>
@endif
