<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <title>{{ config('app.name', 'Noerd') }}</title>
    @if (config('noerd.branding.favicon'))
        <link rel="icon" href="{{ config('noerd.branding.favicon') }}" />
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <x-noerd::noerd-modal-assets />
    <x-noerd::assets />

    <link rel="stylesheet" href="{{ asset('vendor/noerd/fonts/fonts.css') }}" />

    @inject('brandService', 'Noerd\Services\BrandService')

    <style>
        :root {
            --sidebar-apps-width: {{ config('noerd.sidebar.apps_width', '80px') }};
            --sidebar-nav-width: {{ \Noerd\Support\LayoutState::navigationWidth() }};
            --sidebar-total-width: calc(var(--sidebar-apps-width) + var(--sidebar-nav-width));
            --banner-height: 0px;
            --impersonation-banner-height: 0px;
            --environment-banner-height: 0px;

            {{ $brandService->cssCustomProperties() }}
        }

        body {
            font-family: 'Nunito Sans', sans-serif;
            font-optical-sizing: auto;
        }

        /* Stays inline: the check mark's fill carries the brand colour, and a data-URI SVG cannot read a CSS custom property. */
        input[type='checkbox']:checked {
            background-image: url("data:image/svg+xml,%3csvg viewBox='0 0 16 16' fill='{{ str_replace('#', '%23', $brandService->color('brand-primary-text')) }}' xmlns='http://www.w3.org/2000/svg'%3e%3cpath d='M12.207 4.793a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0l-2-2a1 1 0 011.414-1.414L6.5 9.086l4.293-4.293a1 1 0 011.414 0z'/%3e%3c/svg%3e");
        }
    </style>
</head>
<body class="bg-brand-bg h-full">
    <livewire:noerd-modal::noerd-modal />
    {{-- must be loaded before livewire components --}}

    <livewire:noerd::layout.environment-banner />

    @auth(\Noerd\Helpers\NoerdAuth::guardName())
        <livewire:noerd::layout.impersonation-banner />
        <livewire:noerd::layout.banner />
    @endauth

    <div
        class="h-dvh"
        x-data="noerdAppShell(@js([
            'showSidebar' => (bool) $showSidebar,
            'showAppbar' => \Noerd\Support\LayoutState::appBarVisible(),
        ]))"
        @resize.window="handleResize()"
    >
        @inject('navigation', 'Noerd\Services\NavigationService')

        <main
            class="h-full"
            @if (count($navigation->subMenu()) > 0 || count($navigation->blockMenus()) > 0)
                :style="isDesktop
                    ? showSidebar
                        ? showAppbar
                            ? 'padding-left: var(--sidebar-total-width)'
                            : 'padding-left: var(--sidebar-nav-width)'
                        : showAppbar
                          ? 'padding-left: var(--sidebar-apps-width)'
                          : ''
                    : ''"
            @else
                :style="isDesktop && showAppbar ? 'padding-left: var(--sidebar-apps-width)' : ''"
            @endif
        >
            <div class="bg-white min-h-full @auth(\Noerd\Helpers\NoerdAuth::guardName()) pt-[calc(2.9375rem+var(--banner-height,0px)+var(--impersonation-banner-height,0px)+var(--environment-banner-height,0px))] @else pt-[var(--environment-banner-height,0px)] @endauth">
                {{ $slot }}
            </div>
        </main>

        <livewire:noerd::layout.sidebar></livewire:noerd::layout.sidebar>

        @auth(\Noerd\Helpers\NoerdAuth::guardName())
            <livewire:noerd::layout.top-bar></livewire:noerd::layout.top-bar>
        @endauth
    </div>
</body>
</html>
