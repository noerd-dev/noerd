<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ config('app.name', 'Noerd') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <x-noerd::assets/>

    <link rel="stylesheet" href="/vendor/noerd/fonts/fonts.css">

    <style>
        :root {
            --sidebar-apps-width: {{ config('noerd.sidebar.apps_width', '80px') }};
            --sidebar-nav-width: {{ session('sidebar_nav_width', config('noerd.sidebar.navigation_width', '280px')) }};
            --sidebar-total-width: calc(var(--sidebar-apps-width) + var(--sidebar-nav-width));
        }

        body {
            font-family: "Nunito Sans", sans-serif;
            font-optical-sizing: auto;
        }
    </style>
</head>
<body class="bg-brand-bg h-full">

<livewire:framework.noerd-modal/> <!-- must be loaded before livewire components -->

<div class="h-dvh" x-data="{
           openProfile: false,
           isModal: false,
           selectedRow: 0,
           activeList: '',
           showSidebar: '{{$showSidebar}}',
           showAppbar: {{ session('hide_appbar') ? 'false' : 'true' }},
           }">

    @inject('navigation', 'Noerd\Noerd\Services\NavigationService')

    <main class="h-full"
          @if(count($navigation->subMenu()) > 0 || count($navigation->blockMenus()) > 0)
              :style="showSidebar && window.innerWidth >= 1280 ? (showAppbar ? 'padding-left: var(--sidebar-total-width)' : 'padding-left: var(--sidebar-nav-width)') : ''"
          @else
              :style="showSidebar && window.innerWidth >= 1280 && showAppbar ? 'padding-left: var(--sidebar-apps-width)' : ''"
        @endif
    >
        <div class="bg-white h-full @auth pt-11.75 @endauth">
            {{ $slot }}
        </div>
    </main>

    <livewire:layout.sidebar></livewire:layout.sidebar>

    @auth
        <livewire:layout.top-bar></livewire:layout.top-bar>
    @endauth
</div>

</body>
</html>
