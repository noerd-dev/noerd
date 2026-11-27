{{--
    The app bar (the left column with the tenant's app tiles) for a layout of
    a module's OWN design — a screen that is no backend page (an intranet, a
    kiosk) but whose people still switch to their other apps.

    The bar itself is the same <livewire:noerd::layout.app-bar /> the backend
    shows; this host only supplies what `noerd::layouts.app` otherwise does: the
    Alpine state the bar reads, its width, the brand colours and the backend's
    typography — scoped to the bar, so the page keeps its own design and the
    bar looks the same on every screen, whatever the page sets on <body>. The slot is the page; it is
    indented by the bar's width on a desktop and stands below the environment
    banner (local, development, staging), which the host shows like the backend. On a phone the bar is hidden until
    the page dispatches the window event `noerd-app-bar-toggle`.

    The hosting layout loads the noerd assets (`<x-noerd::assets />`) and the
    host's stylesheet with the noerd theme, like every noerd page.
--}}
@props([
    /** CSS width of the bar; the configured backend width when null. */
    'width' => null,
])

@inject('brandService', 'Noerd\Services\BrandService')

@php($appBarWidth = $width ?? config('noerd.sidebar.apps_width', '80px'))

<div
    x-data="{ showAppbar: true, showSidebar: false, isDesktop: window.innerWidth >= 1024 }"
    @resize.window="isDesktop = window.innerWidth >= 1024"
    @noerd-app-bar-toggle.window="showSidebar = ! showSidebar"
    {{ $attributes }}
>
    <link rel="stylesheet" href="{{ asset('vendor/noerd/fonts/fonts.css') }}" />

    {{-- Everything the bar would inherit from the page's <body> is put back to what the backend layout sets. --}}
    <div data-noerd-app-bar-host
         style="--sidebar-apps-width: {{ $appBarWidth }}; {{ $brandService->cssCustomProperties() }}
                font-family: 'Nunito Sans', sans-serif; font-optical-sizing: auto; font-size: 1rem; font-style: normal;
                font-weight: 400; line-height: 1.5; letter-spacing: normal; text-transform: none; color: #000;
                -webkit-font-smoothing: auto; -moz-osx-font-smoothing: auto;">
        <div x-show="showSidebar && ! isDesktop" x-cloak x-transition.opacity
             class="fixed inset-0 z-40 bg-gray-900/80" @click="showSidebar = false"></div>

        {{-- The marker of a local, development or staging installation, as on every backend page. --}}
        <livewire:noerd::layout.environment-banner />

        <livewire:noerd::layout.app-bar />
    </div>

    <div :style="'padding-top: var(--environment-banner-height, 0px);' + (isDesktop ? 'padding-left: {{ $appBarWidth }}' : '')">
        {{ $slot }}
    </div>
</div>
