{{--
    Equal-height container for x-noerd::tab-panel children. All panels share one
    grid cell, so the height always equals the tallest panel (no modal jumping
    between tabs) while each panel scrolls individually once the page body hits
    its max height. Panels must be the only children of this component.
--}}
<div {{ $attributes->merge(['class' => 'grid min-h-0 grid-rows-1 not-last:mb-8 [&>*]:col-start-1 [&>*]:row-start-1']) }}>
    {{ $slot }}
</div>
