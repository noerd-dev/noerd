@props(['number', 'show' => null])

{{--
    A single tab panel inside x-noerd::tab-panels. Hidden panels keep their
    layout space (visibility instead of display) so the container height stays
    constant; each panel is its own scroll container. The optional `show` prop
    takes an Alpine expression that additionally toggles the panel via x-show.
    -mx-6/px-6 keep content aligned while moving the clip edge and scrollbar to
    the page-body padding edge, so input focus rings are not cut off. Flush
    panels (inside an already unpadded wrapper) opt out with `mx-0! px-0!`.
--}}
<div @if($show) x-show="{{ $show }}" @endif
     {{ $attributes->merge(['class' => 'min-h-0 overflow-y-auto -mx-6 px-6']) }}
     :class="currentTab === {{ $number }} ? 'visible' : 'invisible pointer-events-none'">
    {{ $slot }}
</div>
