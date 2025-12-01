<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Noerd') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="stylesheet" href="/vendor/noerd/fonts/fonts.css">

    <style>
        body {
            font-family: "Nunito Sans", sans-serif;
            font-optical-sizing: auto;
        }
    </style>
</head>
<body class="bg-brand-bg h-full">

<livewire:framework.noerd-modal/> <!-- must be loaded before livewire components -->

<div class="h-dvh" x-data="{
           open: false,
           openProfile: false,
           isModal: false,
           selectedRow: 0,
           activeList: '',
           showSidebar: '{{$showSidebar}}',
           }">

    @inject('navigation', 'Noerd\Noerd\Services\NavigationService')

    <!-- Content -->
    <main class="h-full"
          @if(count($navigation->subMenu()) > 0 || count($navigation->blockMenus()) > 0)
              :class="showSidebar ? 'lg:pl-[360px]' : 'lg:pl-[0px]'"
          @else
              :class="showSidebar ? 'lg:pl-[79px]' : 'lg:pl-[0px]'"
        @endif
    >
        <div class="bg-white border border-gray-300 pt-[47px] h-full">
            {{ $slot }}
        </div>
    </main>

    @if(auth()->user()->selectedTenant()?->tenantApps)
        <livewire:layout.sidebar></livewire:layout.sidebar>
    @endif

    <noerdmodal></noerdmodal> <!-- teleport element, do not remove -->

    <livewire:layout.top-bar></livewire:layout.top-bar>
</div>

</body>
</html>
